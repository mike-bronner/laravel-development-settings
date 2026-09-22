<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\FileDiscovery;

function seedSource(string $dir, string $relativePath, string $contents = 'x'): void
{
    $full = $dir . '/' . $relativePath;
    $parent = dirname($full);

    if (! is_dir($parent)) {
        mkdir($parent, 0755, true);
    }

    file_put_contents($full, $contents);
}

it('discovers files in configured directories recursively', function (): void {
    $dir = makeTempDir();
    seedSource($dir, '.ai/guidelines/a.md');
    seedSource($dir, '.ai/skills/demo/SKILL.md');

    $files = (new FileDiscovery)->discover($dir, ['directories' => ['.ai'], 'files' => []]);

    expect(array_keys($files))->toEqualCanonicalizing([
        '.ai/guidelines/a.md',
        '.ai/skills/demo/SKILL.md',
    ]);

    removeTempDir($dir);
});

it('excludes .DS_Store at any depth', function (): void {
    $dir = makeTempDir();
    seedSource($dir, '.ai/a.md');
    seedSource($dir, '.ai/.DS_Store');
    seedSource($dir, '.ai/skills/.DS_Store');

    $files = (new FileDiscovery)->discover($dir, ['directories' => ['.ai'], 'files' => []]);

    expect(array_keys($files))->toBe(['.ai/a.md']);

    removeTempDir($dir);
});

it('excludes files inside a .git directory', function (): void {
    $dir = makeTempDir();
    seedSource($dir, '.ai/a.md');
    seedSource($dir, '.ai/.git/config');

    $files = (new FileDiscovery)->discover($dir, ['directories' => ['.ai'], 'files' => []]);

    expect(array_keys($files))->toBe(['.ai/a.md']);

    removeTempDir($dir);
});

it('includes explicit files and skips missing ones', function (): void {
    $dir = makeTempDir();
    seedSource($dir, 'pint.json');

    $files = (new FileDiscovery)->discover($dir, [
        'directories' => [],
        'files' => ['pint.json', 'does-not-exist.xml'],
    ]);

    expect(array_keys($files))->toBe(['pint.json']);

    removeTempDir($dir);
});

it('keys a keyed file entry by its target and reads it from its source', function (): void {
    $dir = makeTempDir();
    seedSource($dir, 'resources/project/gitignore', 'shipped rules');

    $files = (new FileDiscovery)->discover($dir, [
        'directories' => [],
        'files' => ['resources/project/gitignore' => '.gitignore'],
    ]);

    expect(array_keys($files))->toBe(['.gitignore'])
        ->and($files['.gitignore'])->toBe($dir . '/resources/project/gitignore')
        ->and(file_get_contents($files['.gitignore']))->toBe('shipped rules');

    removeTempDir($dir);
});

it('reads a plain file entry from the path it names, beside a keyed one', function (): void {
    $dir = makeTempDir();
    seedSource($dir, 'resources/project/gitignore', 'shipped rules');
    seedSource($dir, '.gitignore', 'this package\'s own rules');
    seedSource($dir, 'pint.json', '{}');

    $files = (new FileDiscovery)->discover($dir, [
        'directories' => [],
        'files' => ['resources/project/gitignore' => '.gitignore', 'pint.json'],
    ]);

    // The package's own `.gitignore` is never the source of the shipped one,
    // even though it sits at the target path and exists.
    expect($files)->toBe([
        '.gitignore' => $dir . '/resources/project/gitignore',
        'pint.json' => $dir . '/pint.json',
    ]);

    removeTempDir($dir);
});

it('skips a keyed file entry whose source is missing, target present or not', function (): void {
    $dir = makeTempDir();
    seedSource($dir, '.gitignore', 'this package\'s own rules');

    $files = (new FileDiscovery)->discover($dir, [
        'directories' => [],
        'files' => ['resources/project/gitignore' => '.gitignore'],
    ]);

    expect($files)->toBe([]);

    removeTempDir($dir);
});

it('keys a keyed directory entry by its target', function (): void {
    $dir = makeTempDir();
    seedSource($dir, 'resources/shared/skills/demo/SKILL.md');

    $files = (new FileDiscovery)->discover($dir, [
        'directories' => ['resources/shared' => '.ai'],
        'files' => [],
    ]);

    expect($files)->toBe([
        '.ai/skills/demo/SKILL.md' => $dir . '/resources/shared/skills/demo/SKILL.md',
    ]);

    removeTempDir($dir);
});

it('ignores a junk source directory even when the target renames it', function (): void {
    $dir = makeTempDir();
    seedSource($dir, '.git/config');

    // Without the source-side check the rename would launder the junk root
    // past the filter and ship the package's own git internals.
    $files = (new FileDiscovery)->discover($dir, [
        'directories' => ['.git' => 'settings'],
        'files' => [],
    ]);

    expect($files)->toBe([]);

    removeTempDir($dir);
});

it('ignores junk on either side of a mapping', function (): void {
    $dir = makeTempDir();
    seedSource($dir, 'resources/project/.DS_Store');
    seedSource($dir, 'resources/project/gitignore');

    $files = (new FileDiscovery)->discover($dir, [
        'directories' => [],
        'files' => [
            'resources/project/.DS_Store' => 'keep-out.txt',
            'resources/project/gitignore' => '.DS_Store',
        ],
    ]);

    expect($files)->toBe([]);

    removeTempDir($dir);
});

it('skips a missing source directory without error', function (): void {
    $dir = makeTempDir();

    $files = (new FileDiscovery)->discover($dir, ['directories' => ['.ai'], 'files' => []]);

    expect($files)->toBe([]);

    removeTempDir($dir);
});

it('honors a custom ignore list', function (): void {
    $dir = makeTempDir();
    seedSource($dir, '.ai/keep.md');
    seedSource($dir, '.ai/drop.tmp');

    $files = (new FileDiscovery)->discover(
        $dir,
        ['directories' => ['.ai'], 'files' => []],
        ['drop.tmp'],
    );

    expect(array_keys($files))->toBe(['.ai/keep.md']);

    removeTempDir($dir);
});
