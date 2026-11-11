<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

final class ConfigReader
{
    public function read(string $packageDir): PackageConfig
    {
        ini_set('opcache.enable', '0');

        return new PackageConfig(require "{$packageDir}/" . PackageConfig::FILE);
    }
}
