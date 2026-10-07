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

    /**
     * The constant the shim declares, and reads to build its message when
     * Testbench is missing. It is code the shim needs, so an agent stripping
     * comments, or an IDE removing dead code, keeps it.
     */
    public const SHIM_CONSTANT = 'LARAVEL_DEVELOPMENT_SETTINGS_ARTISAN_SHIM';

    /**
     * The whole-line comment that marked the shim up to 0.6.3. Copies still
     * committed downstream carry it.
     */
    public const LEGACY_SHIM_MARKER = '// Written by mike-bronner/laravel-development-settings:'
        . ' runs Artisan through Orchestra Testbench, rooted at this repository.';

    /**
     * The shipped versions of the shim are read from this package's own
     * package manifest, beside the code that reads it.
     */
    public function __construct(
        private string $manifestFile = __DIR__ . '/../../' . self::MANIFEST_FILE,
    ) {
    }

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

    /**
     * The shim constant counts only as code, never in a comment or a string.
     * A shipped version is the shim whatever it holds: a copy an agent
     * stripped of its marker comment is one.
     */
    public function isShim(string $contents): bool
    {
        return $this->namesShimConstant($contents)
            || $this->carriesLegacyMarker($contents)
            || $this->isShippedVersion($contents);
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

    private function namesShimConstant(string $contents): bool
    {
        return collect(token_get_all($contents))
            ->whereStrict(0, T_STRING)
            ->whereStrict(1, self::SHIM_CONSTANT)
            ->isNotEmpty();
    }

    private function carriesLegacyMarker(string $contents): bool
    {
        $line = '/^' . preg_quote(self::LEGACY_SHIM_MARKER, '/') . '\r?$/m';

        return preg_match($line, $contents) === 1;
    }

    private function isShippedVersion(string $contents): bool
    {
        return (new ManifestReader)
            ->read($this->manifestFile)
            ->isKnown(self::ARTISAN, md5($contents));
    }
}
