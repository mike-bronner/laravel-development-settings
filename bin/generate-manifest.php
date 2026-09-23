#!/usr/bin/env php
<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\ContributionDetector;
use MikeBronner\DevelopmentSettings\Support\ManifestGenerator;

$packageDir = dirname(__DIR__);

require $packageDir . '/vendor/autoload.php';

$config = require $packageDir . '/config/development-settings.php';
$ignore = $config['paths']['ignore'] ?? [];
$check = in_array('--check', $argv, true);

$manifests = [
    'manifest.json' => $config,
    ContributionDetector::MANIFEST_FILE => [
        'paths' => ['directories' => $config['capture'] ?? [], 'files' => [], 'ignore' => $ignore],
    ],
];

$stale = [];

foreach ($manifests as $file => $sources) {
    $path = $packageDir . '/' . $file;
    $manifest = (new ManifestGenerator)->generate($packageDir, $sources, $path);

    if ($check) {
        $current = file_exists($path) ? (string) file_get_contents($path) : '';

        if ($current !== $manifest->toJson()) {
            $stale[] = $file;
        }

        continue;
    }

    $manifest->dump($path);

    fwrite(STDOUT, sprintf("%s regenerated (%d paths).\n", $file, count($manifest->paths())));
}

if ($stale !== []) {
    fwrite(STDERR, implode(', ', $stale) . " out of date. Run: composer dev-settings:manifest\n");

    exit(1);
}

if ($check) {
    fwrite(STDOUT, "manifest.json and " . ContributionDetector::MANIFEST_FILE . " are up to date.\n");
}

exit(0);
