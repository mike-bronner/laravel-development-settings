<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\Manifest;

it('returns an empty manifest when the file is missing', function (): void {
    $manifest = Manifest::load('/nonexistent/path/manifest.json');

    expect($manifest->paths())->toBe([]);
});

it('loads checksums from disk', function (): void {
    $dir = makeTempDir();
    $path = $dir . '/manifest.json';
    file_put_contents($path, json_encode(['.ai/a.md' => ['abc'], 'pint.json' => ['def']]));

    $manifest = Manifest::load($path);

    expect($manifest->knownChecksums('.ai/a.md'))->toBe(['abc'])
        ->and($manifest->isKnown('pint.json', 'def'))->toBeTrue();

    removeTempDir($dir);
});

it('appends checksums to a path and dedupes', function (): void {
    $manifest = new Manifest;

    $manifest->record('.ai/a.md', 'v1');
    $manifest->record('.ai/a.md', 'v2');
    $manifest->record('.ai/a.md', 'v1'); // duplicate — ignored

    expect($manifest->knownChecksums('.ai/a.md'))->toBe(['v1', 'v2']);
});

it('retains keys for paths that are no longer recorded (orphan support)', function (): void {
    $manifest = new Manifest(['removed/old.md' => ['stale']]);

    // Recording only a different, current path must NOT drop the old key.
    $manifest->record('.ai/current.md', 'fresh');

    expect($manifest->paths())->toContain('removed/old.md')
        ->and($manifest->knownChecksums('removed/old.md'))->toBe(['stale']);
});

it('reports known vs unknown checksums', function (): void {
    $manifest = new Manifest(['.ai/a.md' => ['known1', 'known2']]);

    expect($manifest->isKnown('.ai/a.md', 'known2'))->toBeTrue()
        ->and($manifest->isKnown('.ai/a.md', 'unknown'))->toBeFalse()
        ->and($manifest->isKnown('missing.md', 'anything'))->toBeFalse();
});

it('dumps with the exact formatting contract (ksort, pretty, unescaped slashes, trailing newline)', function (): void {
    $dir = makeTempDir();
    $path = $dir . '/manifest.json';

    // Insert out of order to prove ksort on dump.
    $manifest = new Manifest;
    $manifest->record('pint.json', 'p1');
    $manifest->record('.ai/guidelines/a.md', 'a1');

    $manifest->dump($path);

    $expected = <<<'JSON'
{
    ".ai/guidelines/a.md": [
        "a1"
    ],
    "pint.json": [
        "p1"
    ]
}

JSON;

    expect(file_get_contents($path))->toBe($expected);

    removeTempDir($dir);
});
