<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use RuntimeException;

final class ClaudeImport
{
    public const FILE = 'CLAUDE.md';

    public const TARGET = 'AGENTS.md';

    public const CREATED = 'created';

    public const NOT_IMPORTED = 'not imported';

    public const FAILED = 'failed';

    private const IMPORT = '@' . self::TARGET;

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
