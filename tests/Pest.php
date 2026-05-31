<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Pest binds a base test case to the test files in the directories listed
| below. These classes are plain PHPUnit test cases — this package is a
| Composer plugin, not a Laravel application, so there is no framework
| TestCase to extend.
|
*/

uses()->in('Unit', 'Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
|
| Create a unique temporary directory for filesystem-centric tests and
| return its absolute path. Callers are responsible for cleanup, or may
| rely on the `tempDir()` helper's registered shutdown removal.
|
*/

function makeTempDir(string $prefix = 'devset-test-'): string
{
    $base = sys_get_temp_dir() . '/' . $prefix . bin2hex(random_bytes(6));

    mkdir(directory: $base, permissions: 0755, recursive: true);

    return $base;
}

function removeTempDir(string $path): void
{
    // Symlinks are unlinked, never followed (so we don't recurse into vendor).
    if (is_link($path)) {
        unlink($path);

        return;
    }

    if (! is_dir($path)) {
        if (file_exists($path)) {
            unlink($path);
        }

        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        removeTempDir($path . '/' . $entry);
    }

    rmdir($path);
}
