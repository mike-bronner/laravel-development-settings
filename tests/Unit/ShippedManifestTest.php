<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\FileDiscovery;
use MikeBronner\DevelopmentSettings\Support\ManagedSection;
use MikeBronner\DevelopmentSettings\Support\Manifest;
use MikeBronner\DevelopmentSettings\Support\PackageRepository;

/*
 * Versions shipped at release tags before manifest.json recorded them. The
 * reverse sync proposed them upstream as local edits (PRs #19 and #21), and
 * the plugin protected them as locally modified. They are the tag backfill's
 * regression guard: each was an untouched copy of a released file.
 */
it('knows the versions older release tags shipped', function (string $path, string $checksum): void {
    $manifest = Manifest::load(dirname(__DIR__, 2) . '/manifest.json');

    expect($manifest->isKnown($path, $checksum))->toBeTrue();
})->with([
    '.gitignore' => ['.gitignore', '91990d02db276ed5bd7a21f2780db4e3'],
    'sync workflow' => ['.github/workflows/sync-developer-settings.yml', '96799ee1dfa802002dd95c4b5203002d'],
]);

/*
 * A managed target is written as its source plus the marker line, so it has to
 * be a tracked file, and its source must end with a newline or the marker
 * would land on the source's last line.
 */
it('ships every managed target as a tracked file whose source ends with a newline', function (string $group): void {
    $root = dirname(__DIR__, 2);
    $paths = (require $root . '/config/development-settings.php')[$group];
    $files = FileDiscovery::trackedPaths($paths['files']);

    expect($paths['managed'])->not->toBe([]);

    foreach ($paths['managed'] as $target) {
        expect($files)->toHaveKey($target)
            ->and(file_get_contents($root . '/' . $files[$target]))->toEndWith("\n");
    }
})->with(['paths', 'package']);

/*
 * The sync writes a managed source above the marker. A source that held the
 * marker itself would give every project two, and every run after that would
 * refuse the file.
 */
it('ships no managed source that holds the sync marker', function (string $group): void {
    $root = dirname(__DIR__, 2);
    $paths = (require $root . '/config/development-settings.php')[$group];
    $files = FileDiscovery::trackedPaths($paths['files']);

    expect($paths['managed'])->not->toBe([]);

    foreach ($paths['managed'] as $target) {
        expect($files)->toHaveKey($target)
            ->and(ManagedSection::markers((string) file_get_contents($root . '/' . $files[$target])))->toBe(0, $target);
    }
})->with(['paths', 'package']);

it('ships no testbench.yaml, because the artisan shim does the rooting', function (): void {
    $root = dirname(__DIR__, 2);
    $files = FileDiscovery::trackedPaths((require $root . '/config/development-settings.php')['paths']['files']);
    $manifest = json_decode((string) file_get_contents($root . '/manifest.json'), associative: true);

    expect($files)->not->toHaveKey('testbench.yaml')
        ->and($manifest)->not->toHaveKey('testbench.yaml');
});

/*
 * Every app holds an artisan of its own. Were the shim a tracked file, or
 * known under manifest.json, copy-sync would call every app's artisan locally
 * modified, and orphan cleanup would offer to delete it.
 */
it('keeps the package files out of the tracked files and out of manifest.json', function (): void {
    $root = dirname(__DIR__, 2);
    $config = require $root . '/config/development-settings.php';
    $targets = array_keys(FileDiscovery::trackedPaths($config['package']['files']));
    $manifest = json_decode((string) file_get_contents($root . '/manifest.json'), associative: true);
    $packageManifest = json_decode((string) file_get_contents($root . '/' . PackageRepository::MANIFEST_FILE), associative: true);

    expect($targets)->toContain(PackageRepository::ARTISAN)
        ->and(array_keys($packageManifest))->toEqualCanonicalizing($targets)
        ->and(array_intersect($targets, array_keys(FileDiscovery::trackedPaths($config['paths']['files']))))->toBe([])
        ->and(array_intersect($targets, array_keys($manifest)))->toBe([]);
});

it('ships a shim that carries its marker', function (): void {
    $root = dirname(__DIR__, 2);
    $source = array_search(PackageRepository::ARTISAN, (require $root . '/config/development-settings.php')['package']['files'], strict: true);

    expect(PackageRepository::isShim((string) file_get_contents($root . '/' . $source)))->toBeTrue();
});
