<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

/**
 * Computes a stable fingerprint of the symlinked source content (e.g. `.ai`).
 *
 * Used to skip re-running Boost when nothing it composes from has changed since
 * the last successful run — avoiding an unnecessary Testbench boot on every
 * `composer install`/`update`. The fingerprint changes whenever a source file
 * is added, removed, or edited (including local in-flow edits).
 */
final class SourceFingerprint
{
    /**
     * @param  array{paths: array{symlinks?: array<string, string>, ignore?: list<string>}}  $config
     */
    public function forSymlinks(string $packageDir, array $config): string
    {
        $ignore = $config['paths']['ignore'] ?? FileDiscovery::DEFAULT_IGNORE;
        $discovery = new FileDiscovery;
        $parts = [];

        foreach ($config['paths']['symlinks'] ?? [] as $linkPath => $sourcePath) {
            $files = $discovery->discover(
                packageDir: $packageDir,
                paths: ['directories' => [$sourcePath], 'files' => []],
                ignore: $ignore,
            );

            foreach ($files as $relativePath => $absolutePath) {
                $key = $linkPath . substr($relativePath, strlen($sourcePath));
                $parts[$key] = (string) md5_file($absolutePath);
            }
        }

        ksort($parts);

        return md5((string) json_encode($parts));
    }
}
