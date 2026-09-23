<?php

declare(strict_types=1);

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
