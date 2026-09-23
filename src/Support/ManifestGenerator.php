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
 *
 * Keys are the project-relative target paths discovery returns, never the
 * package-relative source paths. Moving or renaming a source inside the package
 * therefore leaves the manifest key alone, and downstream orphan cleanup with
 * it.
 */
final class ManifestGenerator
{
    /**
     * @param  array{paths: array{directories?: array<array-key, string>, files?: array<array-key, string>, ignore?: list<string>}}  $config
     */
    public function generate(string $packageDir, array $config, string $manifestPath): Manifest
    {
        $manifest = Manifest::load($manifestPath);

        $files = (new FileDiscovery)->discover(
            packageDir: $packageDir,
            paths: $config['paths'],
            ignore: $config['paths']['ignore'] ?? FileDiscovery::DEFAULT_IGNORE,
        );

        foreach ($files as $targetPath => $absoluteSourcePath) {
            $manifest->record($targetPath, (string) md5_file($absoluteSourcePath));
        }

        return $manifest;
    }
}
