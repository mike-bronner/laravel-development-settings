#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * Regenerates the three shipped manifests from the current sources, or, with
 * `--check`, fails when any of them is out of date.
 */

use MikeBronner\DevelopmentSettings\Support\ManifestFiles;

$packageDir = dirname(__DIR__);

require "{$packageDir}/vendor/autoload.php";

$manifests = new ManifestFiles($packageDir, STDOUT, STDERR);

exit(match (in_array('--check', $argv, strict: true)) {
    true => $manifests->check(),
    false => $manifests->regenerate(),
});
