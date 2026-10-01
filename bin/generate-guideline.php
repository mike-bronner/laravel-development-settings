#!/usr/bin/env php
<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\PhpGuideline;

$packageDir = dirname(__DIR__);

require "{$packageDir}/vendor/autoload.php";

$guideline = new PhpGuideline($packageDir, STDOUT, STDERR);

exit(match (in_array('--check', $argv, strict: true)) {
    true => $guideline->check(),
    false => $guideline->generate(),
});
