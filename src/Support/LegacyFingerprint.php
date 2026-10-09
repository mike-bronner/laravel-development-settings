<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

final class LegacyFingerprint
{
    public const FILE = '.dev-settings-boost';

    public function __construct(private CheckedFile $file = new CheckedFile)
    {
    }

    public function isStale(string $projectDir): bool
    {
        $path = "{$projectDir}/" . self::FILE;

        return ! is_link($path)
            && is_file($path)
            && preg_match('/\A[0-9a-f]{32}\s*\z/', (string) file_get_contents($path)) === 1;
    }

    public function remove(string $projectDir): void
    {
        $file = $this->file;

        match ($this->isStale($projectDir)) {
            true => $file->unlink("{$projectDir}/" . self::FILE),
            false => null,
        };
    }
}
