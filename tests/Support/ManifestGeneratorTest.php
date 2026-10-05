<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\Manifest;
use MikeBronner\DevelopmentSettings\Support\ManifestGenerator;

beforeEach(function (): void {
    $this->package = makeTempDir();
    $this->manifestPath = "{$this->package}/manifest.json";
});

afterEach(function (): void {
    removeTempDir($this->package);
});

it('records every source it discovers, and keeps every checksum it knew', function (
    array $sources,
    array $known,
    array $paths,
    array $expected,
): void {
    seedFiles($this->package, $sources);
    (new Manifest($known))->dump($this->manifestPath);

    $manifest = (new ManifestGenerator)
        ->generate($this->package, ['paths' => $paths], $this->manifestPath);

    expect($manifest->toArray())->toBe($expected);
})->with([
    'a checksum for every discovered source' => [
        ['.ai/guidelines/a.md' => 'alpha', 'pint.json' => '{}'],
        [],
        ['directories' => ['.ai'], 'files' => ['pint.json']],
        ['.ai/guidelines/a.md' => [md5('alpha')], 'pint.json' => [md5('{}')]],
    ],
    'the new checksum after the known ones' => [
        ['.ai/a.md' => 'version 2'],
        ['.ai/a.md' => [md5('version 1')]],
        ['directories' => ['.ai']],
        ['.ai/a.md' => [md5('version 1'), md5('version 2')]],
    ],
    'a source that no longer exists, for orphan cleanup' => [
        ['.ai/current.md' => 'here'],
        ['.ai/deleted.md' => [md5('gone')]],
        ['directories' => ['.ai']],
        ['.ai/current.md' => [md5('here')], '.ai/deleted.md' => [md5('gone')]],
    ],
    'nothing from sources it never copies, such as resources/boost' => [
        ['resources/boost/guidelines/g.md' => 'guide', 'pint.json' => '{}'],
        [],
        ['files' => ['pint.json']],
        ['pint.json' => [md5('{}')]],
    ],
    'a keyed entry under its project target, so a moved source keeps the key' => [
        ['resources/project/gitignore' => 'shipped rules'],
        ['.gitignore' => [md5('earlier shipped rules')]],
        ['files' => ['resources/project/gitignore' => '.gitignore']],
        ['.gitignore' => [md5('earlier shipped rules'), md5('shipped rules')]],
    ],
    'no junk file' => [
        ['.ai/keep.md' => 'keep', '.ai/.DS_Store' => 'junk'],
        [],
        ['directories' => ['.ai'], 'ignore' => ['.DS_Store']],
        ['.ai/keep.md' => [md5('keep')]],
    ],
]);

it('refuses a config that names no paths', function (): void {
    (new ManifestGenerator)->generate($this->package, [], $this->manifestPath);
})->throws(InvalidArgumentException::class, 'names no paths');
