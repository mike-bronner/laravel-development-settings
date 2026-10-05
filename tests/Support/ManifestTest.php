<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\Manifest;

it('appends checksums to a path and dedupes', function (): void {
    $manifest = new Manifest;

    $manifest->record('.ai/a.md', 'v1');
    $manifest->record('.ai/a.md', 'v2');
    $manifest->record('.ai/a.md', 'v1');

    expect($manifest->knownChecksums('.ai/a.md'))->toBe(['v1', 'v2']);
});

it('keeps paths no longer recorded, so orphan cleanup keeps firing', function (): void {
    $manifest = new Manifest(['removed/old.md' => ['stale']]);

    $manifest->record('.ai/current.md', 'fresh');

    expect($manifest->paths())->toContain('removed/old.md')
        ->and($manifest->knownChecksums('removed/old.md'))
        ->toBe(['stale']);
});

it('reports known vs unknown checksums', function (): void {
    $manifest = new Manifest(['.ai/a.md' => ['known1', 'known2']]);

    expect($manifest->isKnown('.ai/a.md', 'known2'))->toBeTrue()
        ->and($manifest->isKnown('.ai/a.md', 'unknown'))
        ->toBeFalse()
        ->and($manifest->isKnown('missing.md', 'anything'))
        ->toBeFalse();
});

it('dumps sorted keys, pretty and with unescaped slashes, ending in a newline', function (): void {
    $dir = makeTempDir();
    $manifest = new Manifest;
    $manifest->record('pint.json', 'p1');
    $manifest->record('.ai/guidelines/a.md', 'a1');

    $manifest->dump("{$dir}/manifest.json");

    expect(file_get_contents("{$dir}/manifest.json"))->toBe(<<<JSON
        {
            ".ai/guidelines/a.md": [
                "a1"
            ],
            "pint.json": [
                "p1"
            ]
        }

        JSON);

    removeTempDir($dir);
});
