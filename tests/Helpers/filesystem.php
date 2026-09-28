<?php

declare(strict_types=1);

// This repository: the package under test.
const REPOSITORY_ROOT = __DIR__ . '/../..';

const TEMP_DIR_ENTROPY_BYTES = 6;

const TEMP_DIR_PERMISSIONS = 0755;

const MODE_UNREADABLE = 0000;

const MODE_READ_ONLY = 0444;

const MODE_WRITABLE = 0644;

const MODE_LOCKED_DIRECTORY = 0555;

/**
 * Create a unique temporary directory for filesystem-centric tests and return
 * its absolute path. Callers remove it with `removeTempDir()`.
 */
function makeTempDir(string $prefix = 'devset-test-'): string
{
    $suffix = bin2hex(random_bytes(TEMP_DIR_ENTROPY_BYTES));
    $base = sys_get_temp_dir() . "/{$prefix}{$suffix}";

    mkdir(directory: $base, permissions: TEMP_DIR_PERMISSIONS, recursive: true);

    return $base;
}

/**
 * Remove a file, a link or a whole directory tree. A symlink is unlinked and
 * never followed, so removal never recurses into what it points at (vendor).
 */
function removeTempDir(string $path): void
{
    match (true) {
        is_link($path) => unlink($path),
        is_dir($path) => removeDirectoryTree($path),
        file_exists($path) => unlink($path),
        default => null,
    };
}

function removeDirectoryTree(string $path): void
{
    foreach (iterator_to_array(new FilesystemIterator($path), false) as $entry) {
        removeTempDir($entry->getPathname());
    }

    rmdir($path);
}

/**
 * Write each file under the directory, creating the directories it needs.
 *
 * @param  array<string, string>  $files  relativePath => contents
 */
function seedFiles(string $dir, array $files): void
{
    foreach ($files as $relativePath => $contents) {
        $path = "{$dir}/{$relativePath}";
        is_dir(dirname($path)) || mkdir(dirname($path), TEMP_DIR_PERMISSIONS, recursive: true);
        file_put_contents($path, $contents);
    }
}
