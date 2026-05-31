<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\FileSync;
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

it('excludes manifest paths under a symlinked root from orphans', function (): void {
    $project = makeTempDir();
    // .ai is symlinked, so its files exist locally but must NOT be treated as orphans.
    seedTree($project, [
        '.ai/guidelines/a.md' => 'shared',
        'old-config.xml' => 'present',
    ]);

    $manifest = new Manifest([
        '.ai/guidelines/a.md' => [md5('shared')],
        'old-config.xml' => [md5('present')],
    ]);

    $orphans = (new FileSync($manifest))->orphans(
        $project,
        discoveredFiles: [],
        symlinkedRoots: ['.ai'],
    );

    expect($orphans)->toBe(['old-config.xml']);

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
