<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

final class ProjectKind
{
    public const MANIFEST_FILE = 'package-manifest.json';

    public const ARTISAN = 'artisan';

    public const TESTBENCH = 'vendor/bin/testbench';

    public const SHIM_CONSTANT = 'LARAVEL_DEVELOPMENT_SETTINGS_ARTISAN_SHIM';

    public const LEGACY_SHIM_MARKER = '// Written by mike-bronner/laravel-development-settings:'
        . ' runs Artisan through Orchestra Testbench, rooted at this repository.';

    public function __construct(
        private string $manifestFile = __DIR__ . '/../../' . self::MANIFEST_FILE,
    ) {
    }

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
        return $this->namesShimConstant($contents)
            || $this->carriesLegacyMarker($contents)
            || $this->isShippedVersion($contents);
    }

    public function hasTestbench(string $projectDir): bool
    {
        return is_file("{$projectDir}/" . self::TESTBENCH);
    }

    public function isTestbenchPackage(string $projectDir): bool
    {
        return ! $this->isApp($projectDir) && $this->hasTestbench($projectDir);
    }

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
