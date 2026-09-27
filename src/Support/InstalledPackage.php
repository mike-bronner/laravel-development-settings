<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

/**
 * This package as a consuming project has it installed: in its vendor
 * directory, under its current name.
 */
final class InstalledPackage
{
    public const NAME = 'mike-bronner/laravel-development-settings';

    /**
     * The name this package shipped under before the repository moved. An
     * upgraded project can still hold it in `boost.json` and in a symlink-era
     * `.ai` link, and only this package can clean those up.
     */
    public const LEGACY_NAME = 'mikebronner/development-settings';

    public function __construct(private string $projectDir)
    {
    }

    /**
     * Where the package is installed, or null when the project does not hold
     * it in vendor.
     */
    public function directory(): ?string
    {
        $vendorDir = "{$this->projectDir}/vendor/" . self::NAME;

        return match (is_dir($vendorDir)) {
            true => (string) realpath($vendorDir),
            false => null,
        };
    }

    /**
     * The config file of the installed package.
     */
    public function config(string $packageDir): PackageConfig
    {
        return new PackageConfig(require "{$packageDir}/" . PackageConfig::FILE);
    }

    /**
     * Whether the project is this package's own repository, which publishes
     * nothing into itself.
     */
    public function isOwnRepository(): bool
    {
        $composerFile = "{$this->projectDir}/composer.json";

        return file_exists($composerFile)
            && data_get($this->decode($composerFile), 'name') === self::NAME;
    }

    private function decode(string $composerFile): mixed
    {
        return json_decode((string) file_get_contents($composerFile), associative: true);
    }
}
