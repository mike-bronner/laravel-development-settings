<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

/**
 * Rebuilds the manifest from the current source files.
 *
 * The existing manifest is loaded first and the current checksum of every
 * discovered file is merged in (append-only). Because deleted source files are
 * never iterated, their existing entries are retained — which is precisely
 * what lets downstream orphan-cleanup keep firing after a file is removed
 * upstream.
 */
final class ManifestGenerator
{
    /**
     * @param  array{paths: array{directories?: list<string>, files?: list<string>, ignore?: list<string>}}  $config
     */
    public function generate(string $packageDir, array $config, string $manifestPath): Manifest
    {
        $manifest = Manifest::load($manifestPath);

        $files = (new FileDiscovery)->discover(
            packageDir: $packageDir,
            paths: $config['paths'],
            ignore: $config['paths']['ignore'] ?? FileDiscovery::DEFAULT_IGNORE,
        );

        foreach ($files as $relativePath => $absolutePath) {
            $manifest->record($relativePath, (string) md5_file($absolutePath));
        }

        return $manifest;
    }
}
