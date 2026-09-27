<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

/**
 * Tells an application from a package repository.
 *
 * A package repository has no artisan of its own. With Orchestra Testbench
 * installed it receives the files under `package` in the config, the artisan
 * shim among them, whose known versions live in their own manifest: an app's
 * real artisan must never meet copy-sync or orphan cleanup.
 */
final class ProjectKind
{
    public const MANIFEST_FILE = 'package-manifest.json';

    public const ARTISAN = 'artisan';

    public const TESTBENCH = 'vendor/bin/testbench';

    public const SHIM_MARKER = '// Written by mike-bronner/laravel-development-settings:'
        . ' runs Artisan through Orchestra Testbench, rooted at this repository.';

    /**
     * Any artisan that is not plainly the shim is an app's, and is never
     * touched: a symlink, a directory, or a file that cannot be read.
     */
    public function isApp(string $projectDir): bool
    {
        $artisan = "{$projectDir}/" . self::ARTISAN;

        return match (true) {
            is_link($artisan) => true,
            ! file_exists($artisan) => false,
            ! is_file($artisan),
            ! is_readable($artisan) => true,
            default => ! $this->isShim((string) file_get_contents($artisan)),
        };
    }

    public function isShim(string $contents): bool
    {
        return preg_match('/^' . preg_quote(self::SHIM_MARKER, '/') . '\r?$/m', $contents) === 1;
    }

    public function hasTestbench(string $projectDir): bool
    {
        return is_file("{$projectDir}/" . self::TESTBENCH);
    }

    /**
     * Where the package files are synced: no artisan of its own, and a
     * Testbench for the shim to boot.
     */
    public function receivesShim(string $projectDir): bool
    {
        return ! $this->isApp($projectDir) && $this->hasTestbench($projectDir);
    }

    /**
     * Whether Boost can run here at all: through an app's artisan, or through
     * the shim in a package repository with Testbench installed.
     */
    public function composesBoost(string $projectDir): bool
    {
        return $this->isApp($projectDir) || $this->hasTestbench($projectDir);
    }
}
