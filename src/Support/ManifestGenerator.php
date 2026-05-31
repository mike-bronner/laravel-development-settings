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
     * @param  array{paths: array{directories?: list<string>, files?: list<string>, symlinks?: array<string, string>, ignore?: list<string>}}  $config
     */
    public function generate(string $packageDir, array $config, string $manifestPath): Manifest
    {
        $manifest = Manifest::load($manifestPath);
        $ignore = $config['paths']['ignore'] ?? FileDiscovery::DEFAULT_IGNORE;
        $discovery = new FileDiscovery;

        // Copied paths.
        $files = $discovery->discover(packageDir: $packageDir, paths: $config['paths'], ignore: $ignore);

        // Symlinked sources are not copied, but they still ship in this package
        // and their checksums are needed for downstream edit-detection. Record
        // them under their link path.
        foreach ($config['paths']['symlinks'] ?? [] as $linkPath => $sourcePath) {
            $sourceFiles = $discovery->discover(
                packageDir: $packageDir,
                paths: ['directories' => [$sourcePath], 'files' => []],
                ignore: $ignore,
            );

            foreach ($sourceFiles as $relativePath => $absolutePath) {
                $key = $linkPath . substr($relativePath, strlen($sourcePath));
                $files[$key] = $absolutePath;
            }
        }

        foreach ($files as $relativePath => $absolutePath) {
            $manifest->record($relativePath, (string) md5_file($absolutePath));
        }

        return $manifest;
    }
}
