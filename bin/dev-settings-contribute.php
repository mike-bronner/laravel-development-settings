#!/usr/bin/env php
<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\ContributeCommand;

$projectDir = (string) getcwd();
$autoload = "{$projectDir}/vendor/autoload.php";
$hasAutoload = is_file($autoload);

$hasAutoload || fwrite(STDERR, "dev-settings-contribute.php: vendor/autoload.php not found.\n");
$hasAutoload || exit(1);

require $autoload;

exit((new ContributeCommand($projectDir, dirname(__DIR__), STDOUT, STDERR))->run());
