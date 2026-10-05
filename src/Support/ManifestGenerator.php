<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use InvalidArgumentException;

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
    private const NO_PATHS = 'The config names no paths to generate from.';

    /**
     * A config with no `paths` is refused rather than read as shipping
     * nothing: the manifest it produced would look up to date while it
     * recorded no file.
     *
     * @param  array{
     *     paths: array{
     *         directories?: array<array-key, string>,
     *         files?: array<array-key, string>,
     *         ignore?: list<string>,
     *     },
     * }  $config
     */
    public function generate(string $packageDir, array $config, string $manifestPath): Manifest
    {
        $paths = data_get($config, 'paths');
        $manifest = (new ManifestReader())->read($manifestPath);

        $files = (new FileDiscovery())->discover(
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
