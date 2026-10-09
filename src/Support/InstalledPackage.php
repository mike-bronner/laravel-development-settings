<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

final class InstalledPackage
{
    public const NAME = 'mike-bronner/laravel-development-settings';

    public const LEGACY_NAME = 'mikebronner/development-settings';

    public function __construct(private string $projectDir)
    {
    }

    public function directory(): ?string
    {
        $vendorDir = "{$this->projectDir}/vendor/" . self::NAME;

        return match (is_dir($vendorDir)) {
            true => (string) realpath($vendorDir),
            false => null,
        };
    }

    public function config(string $packageDir): PackageConfig
    {
        return new PackageConfig(require "{$packageDir}/" . PackageConfig::FILE);
    }

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
