<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\Manifest;
use MikeBronner\DevelopmentSettings\Support\ReverseSync;

function seedProjectFile(string $dir, string $relativePath, string $contents): void
{
    $full = $dir . '/' . $relativePath;

    if (! is_dir(dirname($full))) {
        mkdir(directory: dirname($full), permissions: 0755, recursive: true);
    }

    file_put_contents($full, $contents);
}

/**
 * A manifest with one unrelated entry: the class refuses an empty one.
 */
function unrelatedManifest(): Manifest
{
    return new Manifest(['unrelated.txt' => [md5('unrelated')]]);
}

it('skips a tracked file that matches the current shipped version', function (): void {
    $dir = makeTempDir();
    seedProjectFile($dir, 'pint.json', 'current');
    $sync = new ReverseSync(new Manifest(['pint.json' => [md5('current')]]));

    expect($sync->changedFiles($dir, ['files' => ['pint.json']]))->toBe([]);

    removeTempDir($dir);
});

it('skips a tracked file that matches an older shipped version', function (): void {
    $dir = makeTempDir();
    seedProjectFile($dir, 'pint.json', 'older');
    $sync = new ReverseSync(new Manifest(['pint.json' => [md5('older'), md5('current')]]));

    expect($sync->changedFiles($dir, ['files' => ['pint.json']]))->toBe([]);

    removeTempDir($dir);
});

it('keeps a tracked file whose checksum no shipped version has', function (): void {
    $dir = makeTempDir();
    seedProjectFile($dir, 'pint.json', 'edited');
    $sync = new ReverseSync(new Manifest(['pint.json' => [md5('current')]]));

    expect($sync->changedFiles($dir, ['files' => ['pint.json']]))->toBe(['pint.json' => 'pint.json']);

    removeTempDir($dir);
});

it('keeps a tracked file the manifest has no entry for', function (): void {
    $dir = makeTempDir();
    seedProjectFile($dir, 'phpmd.xml', 'anything');
    $sync = new ReverseSync(unrelatedManifest());

    expect($sync->changedFiles($dir, ['files' => ['phpmd.xml']]))->toBe(['phpmd.xml' => 'phpmd.xml']);

    removeTempDir($dir);
});

it('checks the known version against the project path, not the package path', function (): void {
    $dir = makeTempDir();
    seedProjectFile($dir, '.gitignore', 'shipped');
    $sync = new ReverseSync(new Manifest(['.gitignore' => [md5('shipped')]]));
    $paths = ['files' => ['resources/project/gitignore' => '.gitignore']];

    expect($sync->changedFiles($dir, $paths))->toBe([]);

    file_put_contents($dir . '/.gitignore', 'edited');

    expect($sync->changedFiles($dir, $paths))->toBe(['.gitignore' => 'resources/project/gitignore']);

    removeTempDir($dir);
});

it('skips a tracked file the project does not have', function (): void {
    $dir = makeTempDir();
    $sync = new ReverseSync(unrelatedManifest());

    expect($sync->changedFiles($dir, ['files' => ['pint.json'], 'directories' => ['config']]))->toBe([]);

    removeTempDir($dir);
});

it('checks each file inside a tracked directory on its own', function (): void {
    $dir = makeTempDir();
    seedProjectFile($dir, 'stubs/known.md', 'shipped');
    seedProjectFile($dir, 'stubs/nested/edited.md', 'edited');
    seedProjectFile($dir, 'stubs/added.md', 'new');
    $sync = new ReverseSync(new Manifest([
        'stubs/known.md' => [md5('shipped')],
        'stubs/nested/edited.md' => [md5('shipped')],
    ]));

    $changed = $sync->changedFiles($dir, ['directories' => ['resources/stubs' => 'stubs']]);

    ksort($changed);

    expect($changed)->toBe([
        'stubs/added.md' => 'resources/stubs/added.md',
        'stubs/nested/edited.md' => 'resources/stubs/nested/edited.md',
    ]);

    removeTempDir($dir);
});

it('refuses an empty manifest', function (): void {
    new ReverseSync(new Manifest);
})->throws(RuntimeException::class, 'The manifest records no paths.');

it('refuses a manifest loaded from an unparseable file', function (): void {
    $dir = makeTempDir();
    file_put_contents($dir . '/manifest.json', "<<<<<<< HEAD\n{}\n");

    try {
        new ReverseSync(Manifest::load($dir . '/manifest.json'));
    } finally {
        removeTempDir($dir);
    }
})->throws(RuntimeException::class, 'The manifest records no paths.');

it('skips a tracked file that is a symlink', function (): void {
    $dir = makeTempDir();
    seedProjectFile($dir, 'secret/config', 'token');
    symlink($dir . '/secret/config', $dir . '/pint.json');
    $sync = new ReverseSync(unrelatedManifest());

    expect($sync->changedFiles($dir, ['files' => ['pint.json']]))->toBe([]);

    removeTempDir($dir);
});

it('skips a tracked file reached through a symlinked parent', function (): void {
    $dir = makeTempDir();
    seedProjectFile($dir, 'secret/workflows/sync.yml', 'token');
    symlink($dir . '/secret', $dir . '/.github');
    $sync = new ReverseSync(unrelatedManifest());

    expect($sync->changedFiles($dir, ['files' => ['.github/workflows/sync.yml']]))->toBe([]);

    removeTempDir($dir);
});

it('skips symlinks inside a tracked directory and a tracked directory that is one', function (): void {
    $dir = makeTempDir();
    seedProjectFile($dir, 'secret/config', 'token');
    seedProjectFile($dir, 'stubs/real.md', 'edited');
    symlink($dir . '/secret/config', $dir . '/stubs/linked.md');
    symlink($dir . '/secret', $dir . '/stubs/linked-dir');
    symlink($dir . '/secret', $dir . '/linked-stubs');
    $sync = new ReverseSync(unrelatedManifest());

    $changed = $sync->changedFiles($dir, ['directories' => ['stubs', 'linked-stubs']]);

    expect($changed)->toBe(['stubs/real.md' => 'stubs/real.md']);

    removeTempDir($dir);
});

it('refuses a changed file whose name the line format cannot carry', function (): void {
    $dir = makeTempDir();
    seedProjectFile($dir, "stubs/two\nlines.md", 'edited');
    $sync = new ReverseSync(unrelatedManifest());

    try {
        $sync->changedFiles($dir, ['directories' => ['stubs']]);
    } finally {
        removeTempDir($dir);
    }
})->throws(RuntimeException::class, 'contains a tab or line break');
