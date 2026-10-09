<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use Illuminate\Support\Collection;

final class IgnoreOverrides
{
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

    private function matchesAny(IgnorePattern $pattern, array $candidates): bool
    {
        return collect($candidates)
            ->contains(fn (string $candidate): bool => $pattern->matches($candidate));
    }

    private function isExcluded(array $patterns, string $candidate): bool
    {
        $deciding = collect($patterns)
            ->filter(fn (IgnorePattern $pattern): bool => $pattern->matches($candidate))
            ->last();

        return $deciding instanceof IgnorePattern && ! $deciding->isNegation();
    }

    private function rules(string $contents, int $firstLine): array
    {
        return collect(explode("\n", $contents))
            ->mapWithKeys(fn (string $line, int $index): array => [
                $firstLine + $index => (new IgnorePattern($line))->rule(),
            ])
            ->reject(fn (string $rule): bool => $rule === '' || str_starts_with($rule, '#'))
            ->all();
    }

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
