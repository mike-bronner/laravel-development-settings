<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use RuntimeException;

/**
 * Keeps Claude Code reading the guidelines Boost composes into `AGENTS.md`.
 *
 * The service provider points Boost's Claude Code guidelines at `AGENTS.md`,
 * the file every agent reads, but Claude Code reads `CLAUDE.md`. So a project
 * with no `CLAUDE.md` gets one that only imports `AGENTS.md`. An existing
 * `CLAUDE.md` is the project's and is never written: when it does not import
 * `AGENTS.md`, and Boost did not compose into it during the run, the caller
 * says so instead.
 */
final class ClaudeImport
{
    public const FILE = 'CLAUDE.md';

    public const TARGET = 'AGENTS.md';

    public const CREATED = 'created';

    public const NOT_IMPORTED = 'not imported';

    public const FAILED = 'failed';

    private const IMPORT = '@' . self::TARGET;

    /**
     * What was done or found: one of the constants above, or null when
     * nothing needs saying.
     *
     * @param  list<string>  $composedFiles  the agent files Boost composed this run
     */
    public function settle(string $projectDir, array $composedFiles): ?string
    {
        $claude = "{$projectDir}/" . self::FILE;

        return match (true) {
            is_link($claude),
            in_array(self::FILE, $composedFiles, strict: true) => null,
            ! file_exists($claude) => $this->create($projectDir),
            ! is_file($claude),
            ! is_readable($claude),
            $this->imports((string) file_get_contents($claude)) => null,
            default => self::NOT_IMPORTED,
        };
    }

    /**
     * Only once Boost has written `AGENTS.md`: an import of a file that does
     * not exist would give Claude Code nothing.
     */
    private function create(string $projectDir): ?string
    {
        return match (is_file("{$projectDir}/" . self::TARGET)) {
            true => $this->write("{$projectDir}/" . self::FILE),
            false => null,
        };
    }

    private function write(string $path): string
    {
        try {
            (new CheckedFile)->write($path, self::IMPORT . "\n");
        } catch (RuntimeException) {
            return self::FAILED;
        }

        return self::CREATED;
    }

    private function imports(string $contents): bool
    {
        return preg_match('/(^|\s)' . preg_quote(self::IMPORT, '/') . '(\s|$)/m', $contents) === 1;
    }
}
