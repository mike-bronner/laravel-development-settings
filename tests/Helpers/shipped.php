<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\FileDiscovery;
use MikeBronner\DevelopmentSettings\Support\Manifest;
use MikeBronner\DevelopmentSettings\Support\ManifestReader;
use MikeBronner\DevelopmentSettings\Support\PackageConfig;
use MikeBronner\DevelopmentSettings\Support\ProjectKind;

function shippedConfig(): PackageConfig
{
    return new PackageConfig(require REPOSITORY_ROOT . '/' . PackageConfig::FILE);
}

function shippedManifest(string $file = 'manifest.json'): Manifest
{
    return (new ManifestReader)->read(REPOSITORY_ROOT . "/{$file}");
}

function shippedFiles(): array
{
    return (new FileDiscovery)->trackedPaths(data_get(shippedConfig()->paths(), 'files'));
}

function shippedSource(string $path): string
{
    return (string) file_get_contents(REPOSITORY_ROOT . "/{$path}");
}

function previouslyShippedShim(): string
{
    return (string) file_get_contents(REPOSITORY_ROOT . '/tests/Fixtures/shim/artisan-0.3.4');
}

function strippedShim(): string
{
    return str_replace(ProjectKind::LEGACY_SHIM_MARKER . "\n\n", '', previouslyShippedShim());
}

function retiredGitattributes(): string
{
    return (string) file_get_contents(REPOSITORY_ROOT . '/tests/Fixtures/shim/gitattributes');
}
