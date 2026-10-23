<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\FileSync;
use MikeBronner\DevelopmentSettings\Support\Manifest;

const SHIPPED_V1 = "/vendor\n";

const SHIPPED_V2 = "/vendor\n.env\n";

const LOCAL_LINES = "\n!AGENTS.md\n";

beforeEach(function (): void {
    $this->source = makeTempDir();
    $this->project = makeTempDir();
    seedFiles($this->source, ['gitignore' => SHIPPED_V2, 'sync.yml' => 'workflow']);
    $this->known = new Manifest(['.gitignore' => [md5(SHIPPED_V1), md5(SHIPPED_V2)]]);
    $this->managed = new FileSync($this->known, managed: ['.gitignore']);
});

afterEach(function (): void {
    removeTempDir($this->source);
    removeTempDir($this->project);
});

it('classifies new, unchanged, modified, and updatable files', function (): void {
    seedFiles($this->source, [
        'new.md' => 'brand new',
        'same.md' => 'identical',
        'edited.md' => 'source v2',
        'updatable.md' => 'source v2',
    ]);
    seedFiles($this->project, [
        'same.md' => 'identical',
        'edited.md' => 'local custom',
        'updatable.md' => 'shipped v1',
    ]);
    $files = collect(['new.md', 'same.md', 'edited.md', 'updatable.md'])
        ->mapWithKeys(fn (string $path): array => [$path => "{$this->source}/{$path}"])
        ->all();

    $scan = (new FileSync(new Manifest(['updatable.md' => [md5('shipped v1')]])))
        ->classify($this->project, $files);

    expect(collect($scan)->map(fn (array $group): array => array_keys($group))->all())->toBe([
        'new' => ['new.md'],
        'unchanged' => ['same.md'],
        'modified' => ['edited.md'],
        'updatable' => ['updatable.md'],
        'unmarked' => [],
        'refused' => [],
    ]);
});

it('finds orphans: manifest paths no longer shipped that still exist here', function (): void {
    seedFiles($this->project, ['old.md' => 'present', 'kept.md' => 'present']);
    $manifest = new Manifest([
        'old.md' => [md5('present')],
        'kept.md' => [md5('present')],
        'already-gone.md' => [md5('whatever')],
    ]);

    $orphans = (new FileSync($manifest))
        ->orphans($this->project, ['kept.md' => "{$this->project}/kept.md"]);

    expect($orphans)->toBe(['old.md']);
});

it('never finds an orphan that resolves outside the project', function (): void {
    seedFiles($this->source, ['guidelines/a.md' => 'shared']);
    seedFiles($this->project, ['old-config.xml' => 'present']);
    symlink($this->source, "{$this->project}/.ai");
    $manifest = new Manifest([
        '.ai/guidelines/a.md' => [md5('shared')],
        'old-config.xml' => [md5('present')],
    ]);

    $orphans = (new FileSync($manifest))->safeOrphans($this->project, discoveredFiles: []);

    expect($orphans)->toBe(['old-config.xml'])
        ->and(file_get_contents("{$this->source}/guidelines/a.md"))
        ->toBe('shared');
});

it('finds an orphan through a symlink that stays inside the project', function (): void {
    seedFiles($this->project, ['real/a.md' => 'shipped']);
    symlink("{$this->project}/real", "{$this->project}/linked");

    $sync = new FileSync(new Manifest(['linked/a.md' => [md5('shipped')]]));

    expect($sync->safeOrphans($this->project, discoveredFiles: []))->toBe(['linked/a.md']);
});

it('splits orphans into safe (known md5) and protected (customized)', function (): void {
    seedFiles($this->project, ['pristine.md' => 'shipped', 'tweaked.md' => 'customized']);
    $sync = new FileSync(new Manifest([
        'pristine.md' => [md5('shipped')],
        'tweaked.md' => [md5('shipped')],
    ]));

    expect($sync->safeOrphans($this->project, []))->toBe(['pristine.md'])
        ->and($sync->protectedOrphans($this->project, []))
        ->toBe(['tweaked.md']);
});

it('classifies a managed file above its marker', function (?string $local, string $group): void {
    seedFiles($this->project, match ($local) {
        null => [],
        default => ['.gitignore' => $local],
    });

    $scan = $this->managed
        ->classify($this->project, ['.gitignore' => "{$this->source}/gitignore"]);

    expect(collect($scan)->filter()->keys()->all())->toBe([$group]);
})->with([
    'missing' => [null, 'new'],
    'current, with project lines' => [marked(SHIPPED_V2, LOCAL_LINES), 'unchanged'],
    'older known, with project lines' => [marked(SHIPPED_V1, LOCAL_LINES), 'updatable'],
    'edited above the marker' => [marked("/vendor\nphpunit.xml\n"), 'modified'],
    'unmarked current version' => [SHIPPED_V2, 'updatable'],
    'unmarked older known version' => [SHIPPED_V1, 'updatable'],
    'unmarked and edited' => [SHIPPED_V2 . "!AGENTS.md\n", 'unmarked'],
    'marker twice' => [marked(SHIPPED_V2) . marked('', LOCAL_LINES), 'refused'],
]);

it('classifies a target that is not managed on the whole file', function (): void {
    seedFiles($this->project, ['.gitignore' => marked(SHIPPED_V2, LOCAL_LINES)]);

    $scan = (new FileSync(new Manifest(['.gitignore' => [md5(SHIPPED_V2)]])))
        ->classify($this->project, ['.gitignore' => "{$this->source}/gitignore"]);

    expect(array_keys(data_get($scan, 'modified')))->toBe(['.gitignore']);
});

it('writes a managed target and keeps what the project owns', function (
    ?string $local,
    string $expected,
): void {
    seedFiles($this->project, match ($local) {
        null => [],
        default => ['.gitignore' => $local],
    });

    $this->managed
        ->write($this->project, '.gitignore', "{$this->source}/gitignore");

    expect(file_get_contents("{$this->project}/.gitignore"))->toBe($expected);
})->with([
    'missing: source and marker' => [null, marked(SHIPPED_V2)],
    'marked: project lines kept' => [
        marked(SHIPPED_V1, "\n!AGENTS.md\n/deprecations.log\n"),
        marked(SHIPPED_V2, "\n!AGENTS.md\n/deprecations.log\n"),
    ],
    'marked, edited above: edit replaced, project lines kept' => [
        marked("/vendor\nphpunit.xml\n", LOCAL_LINES),
        marked(SHIPPED_V2, LOCAL_LINES),
    ],
    'unmarked known version: replaced, nothing kept' => [SHIPPED_V1, marked(SHIPPED_V2)],
    'unmarked current version: marker added' => [SHIPPED_V2, marked(SHIPPED_V2)],
    'unmarked edited: whole file kept below the marker' => [
        SHIPPED_V1 . "!AGENTS.md\n",
        marked(SHIPPED_V2, "\n" . SHIPPED_V1 . "!AGENTS.md\n"),
    ],
]);

it('refuses to write a managed target holding the marker twice', function (): void {
    $local = marked(SHIPPED_V2) . marked('', LOCAL_LINES);
    seedFiles($this->project, ['.gitignore' => $local]);

    $source = "{$this->source}/gitignore";

    expect(fn () => $this->managed->write($this->project, '.gitignore', $source))
        ->toThrow(LogicException::class, 'more than once')
        ->and(file_get_contents("{$this->project}/.gitignore"))
        ->toBe($local);
});

it('judges a managed orphan above its marker', function (string $local, string $expected): void {
    seedFiles($this->project, ['.gitignore' => $local]);
    $sync = new FileSync($this->known);

    expect([
        'safe' => $sync->safeOrphans($this->project, []),
        'protected' => $sync->protectedOrphans($this->project, []),
    ])->toBe([...['safe' => [], 'protected' => []], $expected => ['.gitignore']]);
})->with([
    'marked current version, nothing below' => [marked(SHIPPED_V2), 'safe'],
    'marked older version, nothing below' => [marked(SHIPPED_V1), 'safe'],
    'marked known version, project lines below' => [marked(SHIPPED_V2, LOCAL_LINES), 'protected'],
    'edited above the marker' => [marked("/vendor\nphpunit.xml\n"), 'protected'],
    'marker twice' => [marked(SHIPPED_V2) . marked(''), 'protected'],
    'unmarked known version' => [SHIPPED_V1, 'safe'],
    'unmarked and edited' => [SHIPPED_V1 . "!AGENTS.md\n", 'protected'],
]);

it('copies a target that is not managed byte for byte, creating its directory', function (): void {
    $this->managed
        ->write($this->project, '.github/workflows/sync.yml', "{$this->source}/sync.yml");

    expect(file_get_contents("{$this->project}/.github/workflows/sync.yml"))->toBe('workflow');
});

it('throws when a target cannot be written, and leaves it as it was', function (
    array $managed,
    string $failure,
): void {
    seedFiles($this->project, ['.gitignore' => SHIPPED_V1]);
    $sync = new FileSync(new Manifest(['.gitignore' => [md5(SHIPPED_V1)]]), managed: $managed);
    chmod("{$this->project}/.gitignore", MODE_READ_ONLY);

    expect(fn () => $sync->write($this->project, '.gitignore', "{$this->source}/gitignore"))
        ->toThrow(RuntimeException::class, "{$failure} ")
        ->toThrow(RuntimeException::class, 'Permission denied')
        ->and(file_get_contents("{$this->project}/.gitignore"))
        ->toBe(SHIPPED_V1);

    chmod("{$this->project}/.gitignore", MODE_WRITABLE);
})->with([
    'managed' => [['.gitignore'], 'Could not write'],
    'copied' => [[], 'Could not copy'],
]);

it('throws when a target directory cannot be created', function (): void {
    chmod($this->project, MODE_LOCKED_DIRECTORY);

    expect(fn () => (new FileSync(new Manifest))
        ->write($this->project, '.github/workflows/sync.yml', "{$this->source}/sync.yml"))
        ->toThrow(RuntimeException::class, "Could not create {$this->project}/.github/workflows");

    chmod($this->project, TEMP_DIR_PERMISSIONS);
});

const PROJECT_ENV = <<<YAML
    env:
      COMPOSER_AUTH: \${{ secrets.WORKFLOW_PAT }}

    YAML;

it('classifies a project-first file below its marker', function (
    string $local,
    string $group,
): void {
    seedFiles($this->source, ['ci.yml' => WORKFLOW_V2]);
    seedFiles($this->project, ['ci.yml' => $local]);

    $scan = projectFirstSync()
        ->classify($this->project, ['ci.yml' => "{$this->source}/ci.yml"]);

    expect(collect($scan)->filter()->keys()->all())->toBe([$group]);
})->with([
    'current, with project lines' => [markedFirst(PROJECT_ENV, "\n" . WORKFLOW_V2), 'unchanged'],
    'older known, with project lines' => [
        markedFirst(PROJECT_ENV, "\n" . WORKFLOW_V1),
        'updatable',
    ],
    'edited below the marker' => [markedFirst('', "\nname: Mine\n"), 'modified'],
    'unmarked current version' => [WORKFLOW_V2, 'updatable'],
    'unmarked and edited' => [PROJECT_ENV . WORKFLOW_V2, 'unmarked'],
    'the bottom marker only' => [marked(WORKFLOW_V2), 'unmarked'],
    'marker twice' => [markedFirst(PROJECT_ENV) . markedFirst('', "\n" . WORKFLOW_V2), 'refused'],
]);

it('writes a project-first file and keeps the lines above its marker', function (
    ?string $local,
    string $expected,
): void {
    seedFiles($this->source, ['ci.yml' => WORKFLOW_V2]);
    $local === null || seedFiles($this->project, ['ci.yml' => $local]);

    projectFirstSync()->write($this->project, 'ci.yml', "{$this->source}/ci.yml");

    expect(file_get_contents("{$this->project}/ci.yml"))->toBe($expected);
})->with([
    'missing: marker and source' => [null, markedFirst('', "\n" . WORKFLOW_V2)],
    'marked: project lines kept' => [
        markedFirst(PROJECT_ENV, "\n" . WORKFLOW_V1),
        markedFirst(PROJECT_ENV, "\n" . WORKFLOW_V2),
    ],
    'marked, edited below: edit replaced' => [
        markedFirst(PROJECT_ENV, "\nname: Mine\n"),
        markedFirst(PROJECT_ENV, "\n" . WORKFLOW_V2),
    ],
    'unmarked known version: marker added, nothing kept' => [
        WORKFLOW_V1,
        markedFirst('', "\n" . WORKFLOW_V2),
    ],
]);

it('refuses to move an edited project-first file above its marker', function (): void {
    seedFiles($this->source, ['ci.yml' => WORKFLOW_V2]);
    seedFiles($this->project, ['ci.yml' => PROJECT_ENV . WORKFLOW_V1]);

    $source = "{$this->source}/ci.yml";

    expect(fn () => projectFirstSync()->write($this->project, 'ci.yml', $source))
        ->toThrow(LogicException::class, 'ci.yml has no sync marker')
        ->and(file_get_contents("{$this->project}/ci.yml"))
        ->toBe(PROJECT_ENV . WORKFLOW_V1);
});

it('says which managed files keep the project part first', function (): void {
    $sync = new FileSync(new Manifest, managed: ['.gitignore'], projectFirst: ['ci.yml']);

    expect($sync->isProjectFirst('ci.yml'))->toBeTrue()
        ->and($sync->isProjectFirst('.gitignore'))
        ->toBeFalse();
});

it('judges a project-first orphan below its marker', function (
    string $local,
    string $expected,
): void {
    seedFiles($this->project, ['ci.yml' => $local]);
    $sync = new FileSync(new Manifest(['ci.yml' => [md5(WORKFLOW_V1), md5(WORKFLOW_V2)]]));

    expect([
        'safe' => $sync->safeOrphans($this->project, []),
        'protected' => $sync->protectedOrphans($this->project, []),
    ])->toBe([...['safe' => [], 'protected' => []], $expected => ['ci.yml']]);
})->with([
    'known version, nothing above' => [markedFirst('', "\n" . WORKFLOW_V1), 'safe'],
    'known version, project lines above' => [
        markedFirst(PROJECT_ENV, "\n" . WORKFLOW_V2),
        'protected',
    ],
    'edited below the marker' => [markedFirst('', "\nname: Mine\n"), 'protected'],
]);
