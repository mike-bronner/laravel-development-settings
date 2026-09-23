#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * Contributes local edits to this package's installed guideline and skill
 * sources (`resources/boost/…` inside vendor, which no commit in the consuming
 * project carries) back to the development-settings repository as a pull
 * request.
 *
 * Run from a consuming project: `vendor/bin/dev-settings-contribute.php`.
 * Auth: GITHUB token via DEVELOPER_SETTINGS_TOKEN, or a `gh`-authenticated git.
 */

use MikeBronner\DevelopmentSettings\Support\ContributionDetector;
use MikeBronner\DevelopmentSettings\Support\Contributor;
use MikeBronner\DevelopmentSettings\Support\FileDiscovery;
use MikeBronner\DevelopmentSettings\Support\Manifest;
use MikeBronner\DevelopmentSettings\Support\SystemProcess;

$projectDir = getcwd();
$packageDir = dirname(__DIR__);
$autoload = $projectDir . '/vendor/autoload.php';

if (! is_file($autoload)) {
    fwrite(STDERR, "dev-settings-contribute.php: vendor/autoload.php not found.\n");

    exit(1);
}

require $autoload;

$config = require $packageDir . '/config/development-settings.php';
$modified = (new ContributionDetector)->modified(
    packageDir: $packageDir,
    directories: $config['capture'] ?? [],
    sources: Manifest::load($packageDir . '/' . ContributionDetector::MANIFEST_FILE),
    ignore: $config['paths']['ignore'] ?? FileDiscovery::DEFAULT_IGNORE,
);

if ($modified === []) {
    fwrite(STDOUT, "No local development-settings edits to contribute.\n");

    exit(0);
}

$token = getenv('DEVELOPER_SETTINGS_TOKEN') ?: null;
$slug = preg_replace('/[^a-z0-9._-]+/i', '-', basename($projectDir)) ?? 'project';
$branch = 'contribute/' . $slug . '-' . date('YmdHis');
$cloneDir = sys_get_temp_dir() . '/devset-contribute-' . bin2hex(random_bytes(5));

fwrite(STDOUT, sprintf("Contributing %d edited file(s) to %s:\n", count($modified), Contributor::REPO));

foreach (array_keys($modified) as $path) {
    fwrite(STDOUT, '  - ' . $path . "\n");
}

$result = (new Contributor(new SystemProcess))->open(
    modified: $modified,
    branch: $branch,
    cloneDir: $cloneDir,
    token: $token,
);

fwrite($result['status'] === 0 ? STDOUT : STDERR, $result['message'] . "\n");

exit($result['status']);
