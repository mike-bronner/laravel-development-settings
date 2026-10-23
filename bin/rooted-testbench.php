#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Bin;

$invokedAt = (string) reset($argv);
$vendorPath = '/vendor/mike-bronner/laravel-development-settings/bin/rooted-testbench.php';
$isInvokedFromVendor = str_starts_with($invokedAt, '/') && str_ends_with($invokedAt, $vendorPath);

$isInvokedFromVendor && chdir(substr($invokedAt, 0, -strlen($vendorPath)));

$project = (string) getcwd();
$testbench = "{$project}/vendor/bin/testbench";
$isInstalled = is_file($testbench);
$missing = "Run this from a package repository with orchestra/testbench installed:"
    . " {$testbench} does not exist.\n";

$isInstalled || fwrite(STDERR, $missing);
$isInstalled || exit(1);

// phpcs:disable CleanCode.Controversial.Superglobals.Found
$_ENV['APP_BASE_PATH'] = $project;
$_ENV['APP_ENV'] = match (getenv('APP_ENV')) {
    false, '' => 'local',
    default => getenv('APP_ENV'),
};
// phpcs:enable CleanCode.Controversial.Superglobals.Found
putenv("TESTBENCH_WORKING_PATH={$project}");

foreach (['bootstrap/cache', 'storage/framework/views'] as $directory) {
    is_dir("{$project}/{$directory}") || mkdir("{$project}/{$directory}", recursive: true);
}

require $testbench;
