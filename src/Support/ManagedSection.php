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
 * such as `!AGENTS.md` overrides the shipped rule it names.
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
    public const MARKER = '# mike-bronner/laravel-development-settings: project entries go below this line. Anything above it is lost on the next sync.';

    /**
     * How many lines in the contents are the marker.
     */
    public static function markers(string $contents): int
    {
        return (int) preg_match_all(self::pattern(), $contents);
    }

    /**
     * The package's part and the project's part, or null unless the marker
     * appears exactly once. The package's part is every byte above the marker
     * line, so its checksum is the checksum of the source it was written from.
     *
     * @return array{managed: string, project: string}|null
     */
    public static function split(string $contents): ?array
    {
        if (preg_match_all(self::pattern(), $contents, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        [$line, $offset] = $matches[0][0];
        $projectStart = $offset + strlen($line);

        if (($contents[$projectStart] ?? '') === "\n") {
            $projectStart++;
        }

        return [
            'managed' => substr($contents, 0, $offset),
            'project' => substr($contents, $projectStart),
        ];
    }

    /**
     * The shipped source, the marker, then the project's part.
     *
     * A source without a trailing newline would put the marker on its last
     * line, so one is required rather than added: an added byte would make the
     * written part differ from the source, and the next sync would read that as
     * a local edit.
     */
    public static function compose(string $managed, string $project): string
    {
        if ($managed !== '' && ! str_ends_with($managed, "\n")) {
            throw new LogicException('A managed source must end with a newline.');
        }

        return $managed . self::MARKER . "\n" . $project;
    }

    private static function pattern(): string
    {
        return '/^' . preg_quote(self::MARKER, '/') . '[ \t\r]*$/m';
    }
}
