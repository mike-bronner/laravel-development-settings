#!/usr/bin/env php
<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\ManifestFiles;

$packageDir = dirname(__DIR__);

require "{$packageDir}/vendor/autoload.php";

$manifests = new ManifestFiles($packageDir, STDOUT, STDERR);

exit(match (in_array('--check', $argv, strict: true)) {
    true => $manifests->check(),
    false => $manifests->regenerate(),
});
