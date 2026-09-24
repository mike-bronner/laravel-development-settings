<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\FileDiscovery;
use MikeBronner\DevelopmentSettings\Support\Manifest;

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
it('ships every managed target as a tracked file whose source ends with a newline', function (): void {
    $root = dirname(__DIR__, 2);
    $paths = (require $root . '/config/development-settings.php')['paths'];
    $files = FileDiscovery::trackedPaths($paths['files']);

    expect($paths['managed'])->not->toBe([]);

    foreach ($paths['managed'] as $target) {
        expect($files)->toHaveKey($target)
            ->and(file_get_contents($root . '/' . $files[$target]))->toEndWith("\n");
    }
});
