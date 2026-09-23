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

    /**
     * Whether the file was found and removed.
     */
    public function remove(string $projectDir): bool
    {
        $path = $projectDir . '/' . self::FILE;

        if (is_link($path) || ! is_file($path)) {
            return false;
        }

        if (preg_match('/\A[0-9a-f]{32}\s*\z/', (string) file_get_contents($path)) !== 1) {
            return false;
        }

        return unlink($path);
    }
}
