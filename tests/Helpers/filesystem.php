<?php

declare(strict_types=1);

const REPOSITORY_ROOT = __DIR__ . '/../..';

const TEMP_DIR_ENTROPY_BYTES = 6;

const TEMP_DIR_PERMISSIONS = 0755;

const MODE_UNREADABLE = 0000;

const MODE_READ_ONLY = 0444;

const MODE_WRITABLE = 0644;

const MODE_LOCKED_DIRECTORY = 0555;

function makeTempDir(string $prefix = 'devset-test-'): string
{
    $suffix = bin2hex(random_bytes(TEMP_DIR_ENTROPY_BYTES));
    $base = sys_get_temp_dir() . "/{$prefix}{$suffix}";

    mkdir(directory: $base, permissions: TEMP_DIR_PERMISSIONS, recursive: true);

    return $base;
}

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

function seedFiles(string $dir, array $files): void
{
    foreach ($files as $relativePath => $contents) {
        $path = "{$dir}/{$relativePath}";
        is_dir(dirname($path)) || mkdir(dirname($path), TEMP_DIR_PERMISSIONS, recursive: true);
        file_put_contents($path, $contents);
    }
}
