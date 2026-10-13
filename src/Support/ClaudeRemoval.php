<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

final class ClaudeRemoval
{
    public const FILE = 'CLAUDE.md';

    public const GUIDELINES = 'AGENTS.md';

    public function isRemovable(string $projectDir, array $composedFiles): bool
    {
        $claude = "{$projectDir}/" . self::FILE;

        return match (true) {
            in_array(self::FILE, $composedFiles, strict: true) => false,
            is_link($claude) => true,
            default => is_file($claude),
        };
    }

    public function remove(string $projectDir): void
    {
        (new CheckedFile)->unlink("{$projectDir}/" . self::FILE);
    }
}
