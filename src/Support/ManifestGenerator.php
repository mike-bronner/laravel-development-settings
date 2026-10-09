<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use InvalidArgumentException;

final class ManifestGenerator
{
    private const NO_PATHS = 'The config names no paths to generate from.';

    public function generate(string $packageDir, array $config, string $manifestPath): Manifest
    {
        $paths = data_get($config, 'paths');
        $manifest = (new ManifestReader)->read($manifestPath);

        $files = (new FileDiscovery)->discover(
                packageDir: $packageDir,
                paths: match (is_array($paths)) {
                true => $paths,
                false => throw new InvalidArgumentException(self::NO_PATHS),
                },
                ignore: data_get($paths, 'ignore') ?? FileDiscovery::DEFAULT_IGNORE,
            );

        foreach ($files as $targetPath => $absoluteSourcePath) {
            $manifest->record($targetPath, (string) md5_file($absoluteSourcePath));
        }

        return $manifest;
    }
}
