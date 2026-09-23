<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

/**
 * Finds installed guideline and skill sources (`resources/boost/…` inside this
 * package in vendor) whose content matches no version the package ever
 * shipped: files the developer edited in place. These are the candidates for
 * upstream contribution, and a Composer update would overwrite them silently.
 *
 * The known versions come from the capture manifest, never from
 * `manifest.json`. That file drives copy-sync and orphan cleanup, keyed on
 * project paths, so a `resources/boost/…` key there would make cleanup treat a
 * consuming package's own `resources/boost` files as this package's orphans.
 */
final class ContributionDetector
{
    public const MANIFEST_FILE = 'capture-manifest.json';

    /**
     * @param  list<string>  $directories  package-relative source directories
     * @param  list<string>  $ignore
     * @return array<string, string> package-relative path => absolute path
     */
    public function modified(string $packageDir, array $directories, Manifest $sources, array $ignore = FileDiscovery::DEFAULT_IGNORE): array
    {
        $files = (new FileDiscovery)->discover(
            packageDir: $packageDir,
            paths: ['directories' => $directories, 'files' => []],
            ignore: $ignore,
        );

        $modified = [];

        foreach ($files as $relativePath => $absolutePath) {
            if (! $sources->isKnown($relativePath, (string) md5_file($absolutePath))) {
                $modified[$relativePath] = $absolutePath;
            }
        }

        ksort($modified);

        return $modified;
    }
}
