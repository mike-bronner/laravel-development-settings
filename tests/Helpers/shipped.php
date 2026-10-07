<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\FileDiscovery;
use MikeBronner\DevelopmentSettings\Support\Manifest;
use MikeBronner\DevelopmentSettings\Support\ManifestReader;
use MikeBronner\DevelopmentSettings\Support\PackageConfig;
use MikeBronner\DevelopmentSettings\Support\ProjectKind;

/*
 * What this repository ships: its config, its manifests, its sources.
 */

function shippedConfig(): PackageConfig
{
    return new PackageConfig(require REPOSITORY_ROOT . '/' . PackageConfig::FILE);
}

function shippedManifest(string $file = 'manifest.json'): Manifest
{
    return (new ManifestReader)->read(REPOSITORY_ROOT . "/{$file}");
}

/**
 * The tracked files of a group of the config, `paths` or `package`.
 *
 * @return array<string, string> targetPath => sourcePath
 */
function shippedFiles(string $group): array
{
    $entries = data_get(shippedConfig()->entries(PackageConfig::PACKAGE), 'files');

    return (new FileDiscovery)->trackedPaths(match ($group) {
        'package' => $entries,
        default => data_get(shippedConfig()->paths(), 'files'),
    });
}

function shippedSource(string $path): string
{
    return (string) file_get_contents(REPOSITORY_ROOT . "/{$path}");
}

/**
 * The shim as releases from 0.3.4 shipped it, marker comment included.
 */
function previouslyShippedShim(): string
{
    return (string) file_get_contents(REPOSITORY_ROOT . '/tests/Fixtures/shim/artisan-0.3.4');
}

/**
 * The 0.3.4 shim as an agent following the no-comments rule left it: the
 * marker comment and the blank line after it gone.
 */
function strippedShim(): string
{
    return str_replace(ProjectKind::LEGACY_SHIM_MARKER . "\n\n", '', previouslyShippedShim());
}
