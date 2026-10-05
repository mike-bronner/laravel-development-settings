<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\ManifestFiles;
use MikeBronner\DevelopmentSettings\Support\PackageConfig;

const SHIPPED_MANIFESTS = 'manifest.json, capture-manifest.json, package-manifest.json';

beforeEach(function (): void {
    $this->package = makeTempDir();
    $this->output = fopen('php://memory', 'w+b');
    $this->errors = fopen('php://memory', 'w+b');
    seedFiles($this->package, [
        PackageConfig::FILE => '<?php return ' . var_export([
            'capture' => ['resources/boost'],
            'package' => ['files' => ['resources/project/artisan' => 'artisan']],
            'paths' => ['files' => ['pint.json'], 'ignore' => ['.DS_Store']],
        ], true) . ';',
        'pint.json' => '{}',
        'resources/boost/guide.md' => 'guide',
        'resources/project/artisan' => 'shim',
        'resources/boost/.DS_Store' => 'junk',
    ]);
    $this->manifests = new ManifestFiles($this->package, $this->output, $this->errors);
    $this->streams = fn (): array => [
        (string) stream_get_contents($this->output, offset: 0),
        (string) stream_get_contents($this->errors, offset: 0),
    ];
});

afterEach(function (): void {
    removeTempDir($this->package);
});

it('writes each manifest from the sources it records, and says so', function (): void {
    $status = $this->manifests
        ->regenerate();
    $read = fn (string $file): array => json_decode(
            (string) file_get_contents("{$this->package}/{$file}"),
            associative: true,
        );

    expect([$status, ($this->streams)()])->toBe([0, [
        <<<TEXT
            manifest.json regenerated (1 paths).
            capture-manifest.json regenerated (1 paths).
            package-manifest.json regenerated (1 paths).

            TEXT,
        '',
    ]])
        ->and([
            $read('manifest.json'),
            $read('capture-manifest.json'),
            $read('package-manifest.json'),
        ])->toBe([
            ['pint.json' => [md5('{}')]],
            ['resources/boost/guide.md' => [md5('guide')]],
            ['artisan' => [md5('shim')]],
        ]);
});

it('passes the check when every manifest is current', function (): void {
    $this->manifests
        ->regenerate();
    ftruncate($this->output, 0);

    expect([$this->manifests->check(), ($this->streams)()])
        ->toBe([0, [SHIPPED_MANIFESTS . " are up to date.\n", '']]);
});

it('fails the check, naming each manifest a source has changed', function (): void {
    $this->manifests
        ->regenerate();
    ftruncate($this->output, 0);
    file_put_contents("{$this->package}/pint.json", json_encode(['preset' => 'laravel']));
    unlink("{$this->package}/package-manifest.json");

    expect([$this->manifests->check(), ($this->streams)()])->toBe([1, [
        '',
        "manifest.json, package-manifest.json out of date. Run: composer dev-settings:manifest\n",
    ]]);
});
