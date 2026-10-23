<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\ProjectFiles;

beforeEach(function (): void {
    $this->project = makeTempDir('devset-project-files-');
});

afterEach(function (): void {
    removeTempDir($this->project);
});

it('lists every file by its project path', function (): void {
    seedFiles($this->project, ['AGENTS.md' => 'a', '.codex/config.toml' => 'b']);

    $files = data_get((new ProjectFiles)->scan($this->project), 'files');

    expect($files)->toEqualCanonicalizing([
        'AGENTS.md' => "{$this->project}/AGENTS.md",
        '.codex/config.toml' => "{$this->project}/.codex/config.toml",
    ]);
});

it('skips vendor, node_modules, .git and links', function (): void {
    seedFiles($this->project, [
        'kept.json' => '{}',
        'vendor/a.json' => '{}',
        'node_modules/b.json' => '{}',
        '.git/c.json' => '{}',
        'src/vendor/d.json' => '{}',
    ]);
    symlink("{$this->project}/kept.json", "{$this->project}/linked.json");

    $files = data_get((new ProjectFiles)->scan($this->project), 'files');

    expect(array_keys($files))->toBe(['kept.json']);
});

it('lists nothing for a missing directory', function (): void {
    expect((new ProjectFiles)->scan("{$this->project}/missing"))
        ->toBe(['files' => [], 'unreadable' => []]);
});

it('lists the files it can read and names a directory it cannot open', function (): void {
    seedFiles($this->project, [
        'AGENTS.md' => 'a',
        'docker/data/x.md' => 'b',
        'docker/y.md' => 'c',
    ]);
    chmod("{$this->project}/docker/data", MODE_UNREADABLE);

    $scan = (new ProjectFiles)->scan($this->project);
    chmod("{$this->project}/docker/data", TEMP_DIR_PERMISSIONS);

    expect(data_get($scan, 'files'))->toEqualCanonicalizing([
        'AGENTS.md' => "{$this->project}/AGENTS.md",
        'docker/y.md' => "{$this->project}/docker/y.md",
    ])
        ->and(array_keys(data_get($scan, 'unreadable')))
        ->toBe(['docker/data'])
        ->and(data_get($scan, 'unreadable')['docker/data'])
        ->toStartWith("Could not open {$this->project}/docker/data: ");
});
