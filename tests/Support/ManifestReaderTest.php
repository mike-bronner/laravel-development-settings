<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\ManifestReader;

beforeEach(function (): void {
    $this->dir = makeTempDir();
});

afterEach(function (): void {
    removeTempDir($this->dir);
});

it('reads an empty manifest when the file is missing', function (): void {
    $manifest = (new ManifestReader())->read("{$this->dir}/manifest.json");

    expect($manifest->paths())->toBe([]);
});

it('reads checksums from disk', function (): void {
    $checksums = ['.ai/a.md' => ['abc'], 'pint.json' => ['def']];
    file_put_contents("{$this->dir}/manifest.json", json_encode($checksums));

    $manifest = (new ManifestReader())->read("{$this->dir}/manifest.json");

    expect($manifest->knownChecksums('.ai/a.md'))->toBe(['abc'])
        ->and($manifest->isKnown('pint.json', 'def'))
        ->toBeTrue();
});

it('reads an empty manifest from a file that is no JSON object', function (string $contents): void {
    file_put_contents("{$this->dir}/manifest.json", $contents);

    $manifest = (new ManifestReader())->read("{$this->dir}/manifest.json");

    expect($manifest->paths())->toBe([]);
})->with([
    'unparseable' => ['{not json'],
    'a string' => ["\"text\""],
]);
