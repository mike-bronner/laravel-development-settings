<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\FileSync;
use MikeBronner\DevelopmentSettings\Support\ManagedSection;
use MikeBronner\DevelopmentSettings\Support\Manifest;

/**
 * @param  array<string, string>  $files  relativePath => contents
 */
function seedTree(string $dir, array $files): void
{
    foreach ($files as $relativePath => $contents) {
        $full = $dir . '/' . $relativePath;
        $parent = dirname($full);

        if (! is_dir($parent)) {
            mkdir($parent, 0755, true);
        }

        file_put_contents($full, $contents);
    }
}

it('classifies new, unchanged, modified, and updatable files', function (): void {
    $source = makeTempDir();
    $project = makeTempDir();

    // source files
    seedTree($source, [
        'new.md' => 'brand new',
        'same.md' => 'identical',
        'edited.md' => 'source v2',
        'updatable.md' => 'source v2',
    ]);

    // project files (new.md intentionally absent)
    seedTree($project, [
        'same.md' => 'identical',          // matches source -> unchanged
        'edited.md' => 'local custom',      // differs, md5 unknown -> modified
        'updatable.md' => 'shipped v1',     // differs, md5 known -> updatable
    ]);

    $manifest = new Manifest([
        'updatable.md' => [md5('shipped v1')],
    ]);

    $files = [
        'new.md' => $source . '/new.md',
        'same.md' => $source . '/same.md',
        'edited.md' => $source . '/edited.md',
        'updatable.md' => $source . '/updatable.md',
    ];

    $scan = (new FileSync($manifest))->classify($project, $files);

    expect(array_keys($scan['new']))->toBe(['new.md'])
        ->and(array_keys($scan['unchanged']))->toBe(['same.md'])
        ->and(array_keys($scan['modified']))->toBe(['edited.md'])
        ->and(array_keys($scan['updatable']))->toBe(['updatable.md']);

    removeTempDir($source);
    removeTempDir($project);
});

it('finds orphans: manifest paths no longer shipped that still exist locally', function (): void {
    $project = makeTempDir();
    seedTree($project, ['old.md' => 'present', 'kept.md' => 'present']);

    $manifest = new Manifest([
        'old.md' => [md5('present')],
        'kept.md' => [md5('present')],
        'already-gone.md' => [md5('whatever')], // not on disk -> not an orphan
    ]);

    // kept.md is still discovered (shipped); old.md is not.
    $orphans = (new FileSync($manifest))->orphans($project, ['kept.md' => $project . '/kept.md']);

    expect($orphans)->toBe(['old.md']);

    removeTempDir($project);
});

it('excludes orphans that resolve outside the project through a symlink', function (): void {
    $project = makeTempDir();
    $vendorSources = makeTempDir();

    seedTree($vendorSources, ['guidelines/a.md' => 'shared']);
    seedTree($project, ['old-config.xml' => 'present']);

    // How older releases delivered `.ai`: a link into this package in vendor.
    // Deleting through it would destroy the package's own shipped sources.
    symlink($vendorSources, $project . '/.ai');

    $manifest = new Manifest([
        '.ai/guidelines/a.md' => [md5('shared')],
        'old-config.xml' => [md5('present')],
    ]);

    $orphans = (new FileSync($manifest))->safeOrphans($project, discoveredFiles: []);

    expect($orphans)->toBe(['old-config.xml'])
        ->and(file_get_contents($vendorSources . '/guidelines/a.md'))->toBe('shared');

    removeTempDir($project);
    removeTempDir($vendorSources);
});

it('keeps orphans reached through a symlink that stays inside the project', function (): void {
    $project = makeTempDir();

    seedTree($project, ['real/a.md' => 'shipped']);
    symlink($project . '/real', $project . '/linked');

    $manifest = new Manifest(['linked/a.md' => [md5('shipped')]]);

    // The guard is containment, not a blanket refusal to follow symlinks.
    expect((new FileSync($manifest))->safeOrphans($project, discoveredFiles: []))
        ->toBe(['linked/a.md']);

    removeTempDir($project);
});

it('splits orphans into safe (known md5) and protected (customized)', function (): void {
    $project = makeTempDir();
    seedTree($project, [
        'pristine.md' => 'shipped',   // md5 known -> safe
        'tweaked.md' => 'customized', // md5 unknown -> protected
    ]);

    $manifest = new Manifest([
        'pristine.md' => [md5('shipped')],
        'tweaked.md' => [md5('shipped')], // known md5 differs from local -> protected
    ]);

    $sync = new FileSync($manifest);
    $discovered = []; // nothing shipped anymore -> both are orphans

    expect($sync->safeOrphans($project, $discovered))->toBe(['pristine.md'])
        ->and($sync->protectedOrphans($project, $discovered))->toBe(['tweaked.md']);

    removeTempDir($project);
});

/*
 * Managed targets. The sync owns the part above the marker and never reads or
 * rewrites the project's part below it.
 */

const SHIPPED_V1 = "/vendor\n";
const SHIPPED_V2 = "/vendor\n.env\n";

/**
 * A package source at v2, with v1 and v2 known, and a project holding the
 * given `.gitignore` (or none).
 *
 * @return array{0: string, 1: string, 2: FileSync} project dir, source file, sync
 */
function managedFixture(?string $local): array
{
    $source = makeTempDir();
    $project = makeTempDir();

    seedTree($source, ['gitignore' => SHIPPED_V2]);

    if ($local !== null) {
        seedTree($project, ['.gitignore' => $local]);
    }

    $manifest = new Manifest(['.gitignore' => [md5(SHIPPED_V1), md5(SHIPPED_V2)]]);

    return [$project, $source . '/gitignore', new FileSync($manifest, managed: ['.gitignore'])];
}

function managedClass(?string $local): string
{
    [$project, $sourceFile, $sync] = managedFixture($local);

    $scan = $sync->classify($project, ['.gitignore' => $sourceFile]);

    removeTempDir($project);
    removeTempDir(dirname($sourceFile));

    return (string) array_key_first(array_filter($scan));
}

function marked(string $managed, string $project): string
{
    return $managed . ManagedSection::MARKER . "\n" . $project;
}

it('classifies a managed target on the part above its marker only', function (?string $local, string $expected): void {
    expect(managedClass($local))->toBe($expected);
})->with([
    'missing' => [null, 'new'],
    'current, with project lines' => [marked(SHIPPED_V2, "!AGENTS.md\n"), 'unchanged'],
    'older known, with project lines' => [marked(SHIPPED_V1, "!AGENTS.md\n"), 'updatable'],
    'edited above the marker' => [marked("/vendor\nphpunit.xml\n", ''), 'modified'],
    'unmarked current version' => [SHIPPED_V2, 'updatable'],
    'unmarked older known version' => [SHIPPED_V1, 'updatable'],
    'unmarked and edited' => [SHIPPED_V2 . "!AGENTS.md\n", 'unmarked'],
    'marker twice' => [marked(SHIPPED_V2, marked('', "!AGENTS.md\n")), 'refused'],
]);

it('classifies a target that is not managed on the whole file, as before', function (): void {
    [$project, $sourceFile] = managedFixture(marked(SHIPPED_V2, "!AGENTS.md\n"));

    $scan = (new FileSync(new Manifest(['.gitignore' => [md5(SHIPPED_V2)]])))
        ->classify($project, ['.gitignore' => $sourceFile]);

    expect(array_keys($scan['modified']))->toBe(['.gitignore']);

    removeTempDir($project);
    removeTempDir(dirname($sourceFile));
});

it('writes a managed target and keeps what the project owns', function (?string $local, string $expected): void {
    [$project, $sourceFile, $sync] = managedFixture($local);

    $sync->write($project, '.gitignore', $sourceFile);

    expect(file_get_contents($project . '/.gitignore'))->toBe($expected);

    removeTempDir($project);
    removeTempDir(dirname($sourceFile));
})->with([
    'missing: source and marker' => [null, marked(SHIPPED_V2, '')],
    'marked: project lines kept' => [marked(SHIPPED_V1, "!AGENTS.md\n/deprecations.log\n"), marked(SHIPPED_V2, "!AGENTS.md\n/deprecations.log\n")],
    'marked, edited above: edit replaced, project lines kept' => [marked("/vendor\nphpunit.xml\n", "!AGENTS.md\n"), marked(SHIPPED_V2, "!AGENTS.md\n")],
    'unmarked known version: replaced, nothing kept' => [SHIPPED_V1, marked(SHIPPED_V2, '')],
    'unmarked current version: marker added, nothing duplicated' => [SHIPPED_V2, marked(SHIPPED_V2, '')],
    'unmarked edited: whole file kept below the marker' => [SHIPPED_V1 . "!AGENTS.md\n", marked(SHIPPED_V2, SHIPPED_V1 . "!AGENTS.md\n")],
]);

it('refuses to write a managed target holding the marker twice, and leaves it as it was', function (): void {
    $local = marked(SHIPPED_V2, marked('', "!AGENTS.md\n"));
    [$project, $sourceFile, $sync] = managedFixture($local);

    try {
        $sync->write($project, '.gitignore', $sourceFile);
        $threw = false;
    } catch (LogicException $exception) {
        $threw = str_contains($exception->getMessage(), 'more than once');
    }

    expect($threw)->toBeTrue()
        ->and(file_get_contents($project . '/.gitignore'))->toBe($local);

    removeTempDir($project);
    removeTempDir(dirname($sourceFile));
});

it('copies a target that is not managed byte for byte, creating its directory', function (): void {
    $source = makeTempDir();
    $project = makeTempDir();
    seedTree($source, ['sync.yml' => 'workflow']);

    (new FileSync(new Manifest, managed: ['.gitignore']))->write($project, '.github/workflows/sync.yml', $source . '/sync.yml');

    expect(file_get_contents($project . '/.github/workflows/sync.yml'))->toBe('workflow');

    removeTempDir($source);
    removeTempDir($project);
});

it('throws when a target cannot be written, and leaves it as it was', function (bool $managed): void {
    [$project, $sourceFile] = managedFixture(SHIPPED_V1);
    $sync = new FileSync(new Manifest(['.gitignore' => [md5(SHIPPED_V1)]]), managed: $managed ? ['.gitignore'] : []);
    chmod($project . '/.gitignore', 0444);

    try {
        $sync->write($project, '.gitignore', $sourceFile);
        $message = null;
    } catch (RuntimeException $exception) {
        $message = $exception->getMessage();
    } finally {
        chmod($project . '/.gitignore', 0644);
    }

    expect($message)->toStartWith($managed ? 'Could not write ' : 'Could not copy ')
        ->toContain('Permission denied')
        ->and(file_get_contents($project . '/.gitignore'))->toBe(SHIPPED_V1);

    removeTempDir($project);
    removeTempDir(dirname($sourceFile));
})->with(['managed' => [true], 'copied' => [false]]);

it('throws when a target directory cannot be created', function (): void {
    $source = makeTempDir();
    $project = makeTempDir();
    seedTree($source, ['sync.yml' => 'workflow']);
    chmod($project, 0555);

    try {
        (new FileSync(new Manifest))->write($project, '.github/workflows/sync.yml', $source . '/sync.yml');
        $message = null;
    } catch (RuntimeException $exception) {
        $message = $exception->getMessage();
    } finally {
        chmod($project, 0755);
    }

    expect($message)->toStartWith('Could not create ' . $project . '/.github/workflows');

    removeTempDir($source);
    removeTempDir($project);
});
