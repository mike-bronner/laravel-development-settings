<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\FileDiscovery;

beforeEach(function (): void {
    $this->package = makeTempDir();
});

afterEach(function (): void {
    removeTempDir($this->package);
});

it('discovers what the package ships, keyed by target', function (
    array $sources,
    array $paths,
    array $expected,
): void {
    seedFiles($this->package, $sources);

    $files = (new FileDiscovery)->discover($this->package, $paths);

    expect($files)->toEqual(collect($expected)
        ->map(fn (string $source): string => "{$this->package}/{$source}")
        ->all());
})->with([
    'every file under a directory, recursively' => [
        ['.ai/guidelines/a.md' => 'x', '.ai/skills/demo/SKILL.md' => 'x'],
        ['directories' => ['.ai']],
        [
            '.ai/guidelines/a.md' => '.ai/guidelines/a.md',
            '.ai/skills/demo/SKILL.md' => '.ai/skills/demo/SKILL.md',
        ],
    ],
    'no .DS_Store at any depth' => [
        ['.ai/a.md' => 'x', '.ai/.DS_Store' => 'x', '.ai/skills/.DS_Store' => 'x'],
        ['directories' => ['.ai'], 'files' => []],
        ['.ai/a.md' => '.ai/a.md'],
    ],
    'nothing inside a .git directory' => [
        ['.ai/a.md' => 'x', '.ai/.git/config' => 'x'],
        ['files' => [], 'directories' => ['.ai']],
        ['.ai/a.md' => '.ai/a.md'],
    ],
    'the listed files that exist' => [
        ['pint.json' => '{}'],
        ['directories' => [], 'files' => ['pint.json', 'does-not-exist.xml']],
        ['pint.json' => 'pint.json'],
    ],
    'a keyed file under its target' => [
        ['resources/project/gitignore' => 'shipped rules'],
        ['files' => ['resources/project/gitignore' => '.gitignore']],
        ['.gitignore' => 'resources/project/gitignore'],
    ],
    'a keyed directory under its target' => [
        ['resources/shared/skills/demo/SKILL.md' => 'x'],
        ['directories' => ['resources/shared' => '.ai']],
        ['.ai/skills/demo/SKILL.md' => 'resources/shared/skills/demo/SKILL.md'],
    ],
]);

it('reads a plain entry from the path it names, never the package\'s own copy', function (): void {
    seedFiles($this->package, [
        'resources/project/gitignore' => 'shipped rules',
        '.gitignore' => 'this package\'s own rules',
        'pint.json' => '{}',
    ]);

    $files = (new FileDiscovery)->discover($this->package, [
        'files' => ['resources/project/gitignore' => '.gitignore', 'pint.json'],
    ]);

    expect($files)->toBe([
        '.gitignore' => "{$this->package}/resources/project/gitignore",
        'pint.json' => "{$this->package}/pint.json",
    ]);
});

it('discovers nothing missing from the package or ignored', function (array $paths): void {
    seedFiles($this->package, [
        '.gitignore' => 'this package\'s own rules',
        '.git/config' => 'the package\'s own git internals',
        'resources/project/.DS_Store' => 'x',
        'resources/project/gitignore' => 'x',
    ]);

    expect((new FileDiscovery)->discover($this->package, $paths))->toBe([]);
})->with([
    'a keyed file whose source is missing' => [['files' => ['missing' => '.gitignore']]],
    'a junk directory renamed by its target' => [['directories' => ['.git' => 'settings']]],
    'junk as a source' => [['files' => ['resources/project/.DS_Store' => 'kept']]],
    'junk as a target' => [['files' => ['resources/project/gitignore' => '.DS_Store']]],
    'a missing directory' => [['directories' => ['.ai']]],
]);

it('honors a custom ignore list', function (): void {
    seedFiles($this->package, ['.ai/keep.md' => 'x', '.ai/drop.tmp' => 'x']);

    $files = (new FileDiscovery)
        ->discover($this->package, ['directories' => ['.ai']], ['drop.tmp']);

    expect(array_keys($files))->toBe(['.ai/keep.md']);
});

it('reads a list entry as its own source, a keyed one as source to target', function (): void {
    $tracked = (new FileDiscovery)
        ->trackedPaths(['pint.json', 'resources/project/gitignore' => '.gitignore']);

    expect($tracked)->toBe([
        'pint.json' => 'pint.json',
        '.gitignore' => 'resources/project/gitignore',
    ]);
});
