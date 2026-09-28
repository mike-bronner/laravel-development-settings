<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

/**
 * Removes the `.dev-settings-boost` file earlier versions of this package wrote
 * into every project root.
 *
 * The old runner cached an MD5 fingerprint of the symlinked `.ai` sources there,
 * to skip a Boost run when nothing had changed. Nothing reads it any more, and
 * the shipped `.gitignore` no longer ignores it, so left in place it shows up
 * as an untracked file after the upgrade.
 *
 * Only a regular file holding a bare fingerprint is removed. That is the only
 * content this package ever wrote there, so anything else under the same name
 * belongs to the project and is kept.
 */
final class LegacyFingerprint
{
    public const FILE = '.dev-settings-boost';

    public function __construct(private CheckedFile $file = new CheckedFile())
    {
    }

    /**
     * Whether the project holds a fingerprint file this package wrote.
     */
    public function isStale(string $projectDir): bool
    {
        $path = "{$projectDir}/" . self::FILE;

        return ! is_link($path)
            && is_file($path)
            && preg_match('/\A[0-9a-f]{32}\s*\z/', (string) file_get_contents($path)) === 1;
    }

    /**
     * Remove the fingerprint file when it is stale, and throw when it stays.
     */
    public function remove(string $projectDir): void
    {
        $file = $this->file;

        match ($this->isStale($projectDir)) {
            true => $file->unlink("{$projectDir}/" . self::FILE),
            false => null,
        };
    }
}
