<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

/**
 * Finds symlinked-source files (e.g. under `.ai`) whose current content is not
 * a known manifest checksum — i.e. files the developer edited in-flow through
 * the symlink into vendor. These are the candidates for upstream contribution.
 */
final class ContributionDetector
{
    /**
     * @param  array{paths: array{symlinks?: array<string, string>, ignore?: list<string>}}  $config
     * @return array<string, string> relativePath (link-keyed) => absolute source path
     */
    public function modified(string $packageDir, array $config, Manifest $manifest): array
    {
        $ignore = $config['paths']['ignore'] ?? FileDiscovery::DEFAULT_IGNORE;
        $discovery = new FileDiscovery;
        $modified = [];

        foreach ($config['paths']['symlinks'] ?? [] as $linkPath => $sourcePath) {
            $files = $discovery->discover(
                packageDir: $packageDir,
                paths: ['directories' => [$sourcePath], 'files' => []],
                ignore: $ignore,
            );

            foreach ($files as $relativePath => $absolutePath) {
                $key = $linkPath . substr($relativePath, strlen($sourcePath));

                if (! $manifest->isKnown($key, (string) md5_file($absolutePath))) {
                    $modified[$key] = $absolutePath;
                }
            }
        }

        return $modified;
    }
}
