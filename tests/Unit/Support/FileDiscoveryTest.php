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
