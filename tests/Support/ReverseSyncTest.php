<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\CheckedFile;
use MikeBronner\DevelopmentSettings\Support\FileDiscovery;
use MikeBronner\DevelopmentSettings\Support\ManagedSection;
use MikeBronner\DevelopmentSettings\Support\Manifest;
use MikeBronner\DevelopmentSettings\Support\ManifestReader;
use MikeBronner\DevelopmentSettings\Support\ReverseSync;
use MikeBronner\DevelopmentSettings\Tests\Fixtures\ReadCounter;

const SHIPPED = '8407efe4e76e884909955a5e7293661e';

const SHIPPED_VENDOR_RULE = '0c0ef7b9a3271d2fbfa0aca4d0bb61eb';

const MANAGED_PATHS = [
    'files' => ['resources/project/gitignore' => '.gitignore'],
    'managed' => ['.gitignore'],
];

beforeEach(function (): void {
    $this->project = makeTempDir();
    $this->package = makeTempDir();
    $this->unrelated = new ReverseSync(new Manifest(['unrelated.txt' => [md5('unrelated')]]));
    $this->gitignore = new ReverseSync(new Manifest(['.gitignore' => [SHIPPED_VENDOR_RULE]]));
});

afterEach(function (): void {
    removeTempDir($this->project);
    removeTempDir($this->package);
});

it('proposes a file no shipped version matches', function (string $pint, array $expected): void {
    seedFiles($this->project, ['pint.json' => $pint]);
    $sync = new ReverseSync(new Manifest(['pint.json' => [md5('older'), SHIPPED]]));

    expect($sync->changedFiles($this->project, ['files' => ['pint.json']]))->toBe($expected);
})->with([
    'the current shipped version' => ['shipped', []],
    'an older shipped version' => ['older', []],
    'an edit' => ['edited', ['pint.json' => 'pint.json']],
]);

it('proposes a tracked file the manifest has no entry for', function (): void {
    seedFiles($this->project, ['phpstan.neon' => 'anything']);

    expect($this->unrelated->changedFiles($this->project, ['files' => ['phpstan.neon']]))
        ->toBe(['phpstan.neon' => 'phpstan.neon']);
});

it('checks the known version against the project path, not the package path', function (): void {
    seedFiles($this->project, ['.gitignore' => 'shipped']);
    $sync = new ReverseSync(new Manifest(['.gitignore' => [SHIPPED]]));
    $paths = ['files' => ['resources/project/gitignore' => '.gitignore']];
    $unchanged = $sync->changedFiles($this->project, $paths);

    file_put_contents("{$this->project}/.gitignore", 'edited');

    expect($unchanged)->toBe([])
        ->and($sync->changedFiles($this->project, $paths))
        ->toBe(['.gitignore' => 'resources/project/gitignore']);
});

it('skips a tracked file the project does not have', function (): void {
    $paths = ['files' => ['pint.json'], 'directories' => ['config']];

    expect($this->unrelated->changedFiles($this->project, $paths))->toBe([]);
});

it('checks each file inside a tracked directory on its own', function (): void {
    seedFiles($this->project, [
        'stubs/known.md' => 'shipped',
        'stubs/nested/edited.md' => 'edited',
        'stubs/added.md' => 'new',
    ]);
    $sync = new ReverseSync(new Manifest([
        'stubs/known.md' => [SHIPPED],
        'stubs/nested/edited.md' => [SHIPPED],
    ]));

    $paths = ['directories' => ['resources/stubs' => 'stubs']];
    $changed = $sync->changedFiles($this->project, $paths);

    expect($changed)->toEqual([
        'stubs/added.md' => 'resources/stubs/added.md',
        'stubs/nested/edited.md' => 'resources/stubs/nested/edited.md',
    ]);
});

it('refuses an empty manifest before it selects or writes', function (string $method): void {
    seedFiles($this->project, ['pint.json' => 'edited']);
    file_put_contents("{$this->package}/manifest.json", "<<<<<<< HEAD\n{}\n");
    $sync = new ReverseSync((new ManifestReader())->read("{$this->package}/manifest.json"));

    expect(fn () => match ($method) {
        'changedFiles' => $sync->changedFiles($this->project, ['files' => ['pint.json']]),
        'export' => $sync->export($this->project, $this->package, ['files' => ['pint.json']]),
    })->toThrow(RuntimeException::class, 'The manifest records no paths.')
        ->and(file_exists("{$this->package}/pint.json"))
        ->toBeFalse();
})->with(['changedFiles', 'export']);

it('never selects a path reached through a symlink', function (array $paths): void {
    seedFiles($this->project, [
        'secret/config' => 'token',
        'secret/workflows/sync.yml' => 'token',
        'stubs/real.md' => 'edited',
    ]);
    symlink("{$this->project}/secret/config", "{$this->project}/pint.json");
    symlink("{$this->project}/secret", "{$this->project}/.github");
    symlink("{$this->project}/secret/config", "{$this->project}/stubs/linked.md");
    symlink("{$this->project}/secret", "{$this->project}/stubs/linked-dir");
    symlink("{$this->project}/secret", "{$this->project}/linked-stubs");

    expect($this->unrelated->changedFiles($this->project, $paths))->toBe(
        match ($paths) {
            ['directories' => ['stubs']] => ['stubs/real.md' => 'stubs/real.md'],
            default => [],
        },
    );
})->with([
    'a tracked file that is a symlink' => [['files' => ['pint.json']]],
    'a tracked file below a symlinked parent' => [['files' => ['.github/workflows/sync.yml']]],
    'links inside a tracked directory' => [['directories' => ['stubs']]],
    'a tracked directory that is a link' => [['directories' => ['linked-stubs']]],
]);

it('judges a managed file above its marker', function (string $gitignore, array $expected): void {
    seedFiles($this->project, ['.gitignore' => $gitignore]);

    expect($this->gitignore->changedFiles($this->project, MANAGED_PATHS))->toBe($expected);
})->with([
    'project lines below the marker' => [marked("/vendor\n", "\n!AGENTS.md\nphpunit.xml\n"), []],
    'an edit above the marker' => [
        marked("/vendor\n/storage\n", "\n!AGENTS.md\n"),
        ['.gitignore' => 'resources/project/gitignore'],
    ],
    'no marker, edited' => ["/vendor\n!AGENTS.md\n", []],
    'the marker twice' => [marked("/storage\n") . marked('', "\n!AGENTS.md\n"), []],
]);

it('exports only the part above the marker of a managed target', function (): void {
    seedFiles($this->project, ['.gitignore' => marked("/vendor\n/storage\n", "\n!AGENTS.md\n")]);

    $exported = $this->gitignore
        ->export($this->project, $this->package, MANAGED_PATHS);

    expect($exported)->toBe(['.gitignore' => 'resources/project/gitignore'])
        ->and(file_get_contents("{$this->package}/resources/project/gitignore"))
        ->toBe("/vendor\n/storage\n");
});

it('exports a changed file that is not managed whole, and nothing unchanged', function (): void {
    $workflow = marked("edited\n", "\nkept\n");
    seedFiles($this->project, [
        '.github/workflows/sync.yml' => $workflow,
        'pint.json' => 'shipped',
    ]);
    $sync = new ReverseSync(new Manifest(['pint.json' => [SHIPPED]]));
    $paths = ['files' => ['.github/workflows/sync.yml', 'pint.json'], 'managed' => ['.gitignore']];

    $exported = $sync->export($this->project, $this->package, $paths);

    expect($exported)->toBe(['.github/workflows/sync.yml' => '.github/workflows/sync.yml'])
        ->and(file_get_contents("{$this->package}/.github/workflows/sync.yml"))
        ->toBe($workflow)
        ->and(file_exists("{$this->package}/pint.json"))
        ->toBeFalse();
});

it('reads each changed project file once while exporting it', function (): void {
    seedFiles($this->project, [
        '.gitignore' => marked("/vendor\n/storage\n", "\n!AGENTS.md\n"),
        'pint.json' => 'edited',
    ]);
    $paths = [
        ...MANAGED_PATHS,
        'files' => ['resources/project/gitignore' => '.gitignore', 'pint.json'],
    ];
    $root = (string) realpath($this->project);
    class_exists(CheckedFile::class);
    class_exists(FileDiscovery::class);
    class_exists(ManagedSection::class);

    $reads = (new ReadCounter())
        ->watch(fn (): array => $this->gitignore->export($this->project, $this->package, $paths));

    expect($reads)->toBe(["{$root}/.gitignore" => 1, "{$root}/pint.json" => 1])
        ->and(file_get_contents("{$this->package}/resources/project/gitignore"))
        ->toBe("/vendor\n/storage\n")
        ->and(file_get_contents("{$this->package}/pint.json"))
        ->toBe('edited');
});

it('throws when a changed file cannot be written to the package checkout', function (): void {
    seedFiles($this->project, ['pint.json' => 'edited']);
    seedFiles($this->package, ['pint.json' => 'shipped']);
    chmod("{$this->package}/pint.json", MODE_READ_ONLY);

    $paths = ['files' => ['pint.json']];

    expect(fn () => $this->unrelated->export($this->project, $this->package, $paths))
        ->toThrow(RuntimeException::class, 'Could not write');

    chmod("{$this->package}/pint.json", MODE_WRITABLE);
});

it('throws when a changed file needs a directory the package cannot hold', function (): void {
    seedFiles($this->project, ['.github/workflows/sync.yml' => 'edited']);
    chmod($this->package, MODE_LOCKED_DIRECTORY);
    $paths = ['files' => ['.github/workflows/sync.yml']];

    expect(fn () => $this->unrelated->export($this->project, $this->package, $paths))
        ->toThrow(RuntimeException::class, 'Could not create');

    chmod($this->package, TEMP_DIR_PERMISSIONS);
});

it('throws when a tracked file in the project cannot be read', function (): void {
    seedFiles($this->project, ['pint.json' => 'edited']);
    chmod("{$this->project}/pint.json", MODE_UNREADABLE);

    expect(fn () => $this->unrelated->changedFiles($this->project, ['files' => ['pint.json']]))
        ->toThrow(RuntimeException::class, 'Could not read');

    chmod("{$this->project}/pint.json", MODE_WRITABLE);
});
