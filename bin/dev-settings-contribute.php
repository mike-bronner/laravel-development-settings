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

use MikeBronner\DevelopmentSettings\Support\ContributeCommand;

$projectDir = (string) getcwd();
$autoload = "{$projectDir}/vendor/autoload.php";
$hasAutoload = is_file($autoload);

$hasAutoload || fwrite(STDERR, "dev-settings-contribute.php: vendor/autoload.php not found.\n");
$hasAutoload || exit(1);

require $autoload;

exit((new ContributeCommand($projectDir, dirname(__DIR__), STDOUT, STDERR))->run());
