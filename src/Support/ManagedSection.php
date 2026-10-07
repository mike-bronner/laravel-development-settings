<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use LogicException;

/**
 * Splits a managed file into the part this package owns and the part the
 * consuming project owns, at a single marker line.
 *
 * Everything above the marker is the shipped source, replaced on every sync.
 * Everything below it is the project's, and the sync never reads it as an edit
 * to the source and never rewrites it. The project's part comes last on
 * purpose: in an ignore file the last matching rule wins, so a project line
 * such as `!GEMINI.md` overrides the shipped rule it names.
 *
 * The marker is recognised only as a whole line, so a comment that merely
 * mentions it is not one. A file holding it more than once has no answer to
 * "where does the project's part start", so it does not split: callers leave
 * it untouched.
 *
 * The reverse sync workflow requires this file directly, with no Composer
 * install, so it stays free of dependencies.
 */
final class ManagedSection
{
    public const MARKER = <<<MARKER
        # mike-bronner/laravel-development-settings: project entries go below this line.
        MARKER . ' Anything above it is lost on the next sync.';

    /**
     * How many lines in the contents are the marker.
     */
    public function markers(string $contents): int
    {
        return (int) preg_match_all($this->pattern(), $contents);
    }

    /**
     * The package's part and the project's part, or null unless the marker
     * appears exactly once. The package's part is every byte above the marker
     * line, so its checksum is the checksum of the source it was written from.
     *
     * @return array{managed: string, project: string}|null
     */
    public function split(string $contents): ?array
    {
        $markers = preg_match_all($this->pattern(), $contents, $matches, PREG_OFFSET_CAPTURE);

        return match ($markers) {
            1 => $this->parts($contents, $matches),
            default => null,
        };
    }

    /**
     * The package's part, or null unless the marker appears exactly once.
     */
    public function managedPart(string $contents): ?string
    {
        ['managed' => $managed] = $this->split($contents) ?? ['managed' => null];

        return $managed;
    }

    /**
     * The project's part, or null unless the marker appears exactly once.
     */
    public function projectPart(string $contents): ?string
    {
        ['project' => $project] = $this->split($contents) ?? ['project' => null];

        return $project;
    }

    /**
     * The shipped source, the marker, then the project's part.
     *
     * A source without a trailing newline would put the marker on its last
     * line, so one is required rather than added: an added byte would make the
     * written part differ from the source, and the next sync would read that as
     * a local edit.
     */
    public function compose(string $managed, string $project): string
    {
        return match (true) {
            $managed === '',
            str_ends_with($managed, "\n") => $managed . self::MARKER . "\n{$project}",
            default => throw new LogicException('A managed source must end with a newline.'),
        };
    }

    /**
     * @param  array<int, list<array{string, int}>>  $matches  the one marker match, with its offset
     * @return array{managed: string, project: string}
     */
    private function parts(string $contents, array $matches): array
    {
        [[[$line, $offset]]] = $matches;
        $lineEnd = $offset + strlen($line);

        return [
            'managed' => substr($contents, 0, $offset),
            'project' => substr($contents, $this->projectStart($contents, $lineEnd)),
        ];
    }

    /**
     * The project's part starts after the newline ending the marker line, or
     * at the end of the contents when the marker is the last line.
     */
    private function projectStart(string $contents, int $lineEnd): int
    {
        return match (substr($contents, $lineEnd, 1)) {
            "\n" => $lineEnd + 1,
            default => $lineEnd,
        };
    }

    private function pattern(): string
    {
        return '/^' . preg_quote(self::MARKER, '/') . '[ \t\r]*$/m';
    }
}
