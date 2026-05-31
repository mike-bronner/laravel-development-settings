<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\Manifest;
use MikeBronner\DevelopmentSettings\Support\ManifestGenerator;

/**
 * @param  array{directories?: list<string>, files?: list<string>, ignore?: list<string>}  $paths
 */
function generatorConfig(array $paths): array
{
    return ['paths' => $paths];
}

it('records a checksum for every discovered source file', function (): void {
    $pkg = makeTempDir();
    mkdir($pkg . '/.ai/guidelines', 0755, true);
    file_put_contents($pkg . '/.ai/guidelines/a.md', 'alpha');
    file_put_contents($pkg . '/pint.json', '{}');

    $manifestPath = $pkg . '/manifest.json';

    $manifest = (new ManifestGenerator)->generate(
        $pkg,
        generatorConfig(['directories' => ['.ai'], 'files' => ['pint.json']]),
        $manifestPath,
    );

    expect($manifest->knownChecksums('.ai/guidelines/a.md'))->toBe([md5('alpha')])
        ->and($manifest->knownChecksums('pint.json'))->toBe([md5('{}')]);

    removeTempDir($pkg);
});

it('appends new checksums while retaining existing ones (append-only)', function (): void {
    $pkg = makeTempDir();
    mkdir($pkg . '/.ai', 0755, true);
    file_put_contents($pkg . '/.ai/a.md', 'version 2');

    // Pre-existing manifest already knows an older checksum for the same path.
    $manifestPath = $pkg . '/manifest.json';
    (new Manifest(['.ai/a.md' => [md5('version 1')]]))->dump($manifestPath);

    $manifest = (new ManifestGenerator)->generate(
        $pkg,
        generatorConfig(['directories' => ['.ai'], 'files' => []]),
        $manifestPath,
    );

    expect($manifest->knownChecksums('.ai/a.md'))->toBe([md5('version 1'), md5('version 2')]);

    removeTempDir($pkg);
});

it('retains entries for source files that no longer exist (orphan support)', function (): void {
    $pkg = makeTempDir();
    mkdir($pkg . '/.ai', 0755, true);
    file_put_contents($pkg . '/.ai/current.md', 'here');

    // Manifest remembers a guideline that has since been deleted from source.
    $manifestPath = $pkg . '/manifest.json';
    (new Manifest(['.ai/deleted.md' => [md5('gone')]]))->dump($manifestPath);

    $manifest = (new ManifestGenerator)->generate(
        $pkg,
        generatorConfig(['directories' => ['.ai'], 'files' => []]),
        $manifestPath,
    );

    expect($manifest->paths())->toContain('.ai/deleted.md')
        ->and($manifest->knownChecksums('.ai/deleted.md'))->toBe([md5('gone')])
        ->and($manifest->paths())->toContain('.ai/current.md');

    removeTempDir($pkg);
});

it('records symlinked sources under their link path', function (): void {
    $pkg = makeTempDir();
    mkdir($pkg . '/.ai/guidelines', 0755, true);
    file_put_contents($pkg . '/.ai/guidelines/g.md', 'guide');

    $manifest = (new ManifestGenerator)->generate(
        $pkg,
        generatorConfig(['directories' => [], 'files' => [], 'symlinks' => ['.ai' => '.ai']]),
        $pkg . '/manifest.json',
    );

    expect($manifest->knownChecksums('.ai/guidelines/g.md'))->toBe([md5('guide')]);

    removeTempDir($pkg);
});

it('excludes junk files from the generated manifest', function (): void {
    $pkg = makeTempDir();
    mkdir($pkg . '/.ai', 0755, true);
    file_put_contents($pkg . '/.ai/keep.md', 'keep');
    file_put_contents($pkg . '/.ai/.DS_Store', 'junk');

    $manifest = (new ManifestGenerator)->generate(
        $pkg,
        generatorConfig(['directories' => ['.ai'], 'files' => [], 'ignore' => ['.DS_Store']]),
        $pkg . '/manifest.json',
    );

    expect($manifest->paths())->toBe(['.ai/keep.md']);

    removeTempDir($pkg);
});
