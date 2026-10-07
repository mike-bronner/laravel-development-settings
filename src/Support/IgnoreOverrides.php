<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use Illuminate\Support\Collection;

/**
 * Finds the lines below the marker of a managed `.gitignore` that override
 * the shipped rules above it. The last matching rule wins in an ignore file,
 * so a project line can undo what the shipped block leaves tracked on purpose.
 * Converting an edited file moves the whole old file below the marker, old
 * shipped rules included, which is how most of these lines get there.
 *
 * Nothing is changed: every line below the marker is the project's. Each
 * rule is read by `IgnorePattern`. As in git, the directories above a path
 * are decided first, from the top: once the last project line matching one
 * of them ignores it, nothing below it can be re-included. Otherwise the last
 * project line matching the path itself decides it.
 */
final class IgnoreOverrides
{
    /**
     * What the shipped `.gitignore` leaves tracked on purpose, each with a
     * path inside it to test the rules against.
     */
    public const TRACKED_ON_PURPOSE = [
        '.ai/' => '.ai/guidelines/example.md',
        'AGENTS.md' => 'AGENTS.md',
        'CLAUDE.md' => 'CLAUDE.md',
        'bootstrap/cache/.gitignore' => 'bootstrap/cache/.gitignore',
        'storage/framework/.gitignore' => 'storage/framework/.gitignore',
        'storage/framework/views/.gitignore' => 'storage/framework/views/.gitignore',
        'storage/logs/.gitignore' => 'storage/logs/.gitignore',
    ];

    public const REPEATS = 'repeats a shipped rule';

    public const IGNORES = 'ignores %s, which the shipped rules leave tracked';

    /**
     * The overriding lines, by their line number in the whole file. A file
     * without exactly one marker has no project part, and yields none.
     *
     * @return array<int, array{string, list<string>}> lineNumber => [line, reasons]
     */
    public function find(string $contents, string $shipped): array
    {
        $split = (new ManagedSection)->split($contents);
        $markerLine = substr_count((string) data_get($split, 'managed'), "\n") + 1;

        return match ($split) {
            null => [],
            default => $this->overrides(
                $this->rules((string) data_get($split, 'project'), $markerLine + 1),
                $this->rules($shipped, 1),
            ),
        };
    }

    /**
     * @param  array<int, string>  $project  lineNumber => rule
     * @param  array<int, string>  $shipped  lineNumber => rule
     * @return array<int, array{string, list<string>}>
     */
    private function overrides(array $project, array $shipped): array
    {
        $patterns = collect($project)
            ->map(fn (string $rule): IgnorePattern => new IgnorePattern($rule))
            ->all();
        $ignoring = collect(self::TRACKED_ON_PURPOSE)
            ->map(fn (string $path): array => $this->ignoringLines($patterns, $path));

        return collect($project)
            ->map(fn (string $rule, int $line): array => [
                $rule,
                $this->reasons($rule, $line, $shipped, $ignoring),
            ])
            ->reject(fn (array $override): bool => data_get($override, 1) === [])
            ->all();
    }

    /**
     * @param  array<int, string>  $shipped
     * @param  Collection<string, list<int>>  $ignoring  label => the lines ignoring it
     * @return list<string>
     */
    private function reasons(string $rule, int $line, array $shipped, Collection $ignoring): array
    {
        $repeats = match (in_array($rule, $shipped, strict: true)) {
            true => [self::REPEATS],
            false => [],
        };
        $ignored = $ignoring
            ->filter(fn (array $lines): bool => in_array($line, $lines, strict: true))
            ->keys()
            ->map(fn (string $label): string => sprintf(self::IGNORES, $label))
            ->all();

        return [...$repeats, ...$ignored];
    }

    /**
     * Every project line that ignores the path or a directory above it, when
     * the project's lines leave the path ignored. A later negation that
     * re-includes the path, or the directory above it, leaves none.
     *
     * @param  array<int, IgnorePattern>  $patterns  lineNumber => pattern
     * @return list<int>
     */
    private function ignoringLines(array $patterns, string $path): array
    {
        $candidates = $this->candidates($path);
        $isIgnored = collect($candidates)
            ->contains(fn (string $candidate): bool => $this->isExcluded($patterns, $candidate));

        return match ($isIgnored) {
            true => collect($patterns)
                ->reject(fn (IgnorePattern $pattern): bool => $pattern->isNegation())
                ->filter(fn (IgnorePattern $pattern): bool => $this->matchesAny(
                    $pattern,
                    $candidates,
                ))
                ->keys()
                ->all(),
            false => [],
        };
    }

    /**
     * @param  list<string>  $candidates
     */
    private function matchesAny(IgnorePattern $pattern, array $candidates): bool
    {
        return collect($candidates)
            ->contains(fn (string $candidate): bool => $pattern->matches($candidate));
    }

    /**
     * @param  array<int, IgnorePattern>  $patterns
     */
    private function isExcluded(array $patterns, string $candidate): bool
    {
        $deciding = collect($patterns)
            ->filter(fn (IgnorePattern $pattern): bool => $pattern->matches($candidate))
            ->last();

        return $deciding instanceof IgnorePattern && ! $deciding->isNegation();
    }

    /**
     * The rules of an ignore file, without blank lines and comments.
     *
     * @return array<int, string> lineNumber => rule
     */
    private function rules(string $contents, int $firstLine): array
    {
        return collect(explode("\n", $contents))
            ->mapWithKeys(fn (string $line, int $index): array => [
                $firstLine + $index => (new IgnorePattern($line))->rule(),
            ])
            ->reject(fn (string $rule): bool => $rule === '' || str_starts_with($rule, '#'))
            ->all();
    }

    /**
     * The path and every directory above it, which a rule can ignore it by.
     * A directory ends with a slash.
     *
     * @return list<string>
     */
    private function candidates(string $path): array
    {
        $segments = explode('/', $path);
        $directories = collect(array_slice($segments, 0, -1))
            ->keys()
            ->map(fn (int $index): string => implode(
                    '/',
                    array_slice($segments, 0, $index + 1),
                ) . '/')
            ->all();

        return [...$directories, $path];
    }
}
