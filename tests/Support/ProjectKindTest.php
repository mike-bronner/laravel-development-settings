<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\ProjectKind;

beforeEach(function (): void {
    $this->project = makeTempDir('devset-repository-');
    $this->kind = new ProjectKind;
});

afterEach(function (): void {
    removeTempDir($this->project);
});

it('tells an app by an artisan that carries no shim marker', function (string $artisan): void {
    file_put_contents("{$this->project}/artisan", $artisan);

    expect($this->kind->isApp($this->project))->toBeTrue();
})->with([
    'an app artisan' => ["#!/usr/bin/env php\n<?php\n"],
    'the marker inside a longer line' => ['// See: ' . ProjectKind::SHIM_MARKER . "\n"],
]);

it('tells no app where there is no artisan, or it is the shim', function (?string $artisan): void {
    seedFiles($this->project, collect(['artisan' => $artisan])->whereNotNull()->all());

    expect($this->kind->isApp($this->project))->toBeFalse();
})->with([
    'no artisan' => [null],
    'the shim' => ["#!/usr/bin/env php\n<?php\n\n" . ProjectKind::SHIM_MARKER . "\n"],
    'the shim with CRLF endings' => ["#!/usr/bin/env php\r\n" . ProjectKind::SHIM_MARKER . "\r\n"],
]);

it('treats an artisan it cannot read as the shim as an app', function (string $shape): void {
    file_put_contents("{$this->project}/elsewhere", ProjectKind::SHIM_MARKER . "\n");
    seedFiles($this->project, [ProjectKind::TESTBENCH => "<?php\n"]);

    match ($shape) {
        'symlink to the shim' => symlink("{$this->project}/elsewhere", "{$this->project}/artisan"),
        'dangling symlink' => symlink("{$this->project}/missing", "{$this->project}/artisan"),
        'directory' => mkdir("{$this->project}/artisan"),
    };

    expect($this->kind->isApp($this->project))->toBeTrue()
        ->and($this->kind->receivesShim($this->project))
        ->toBeFalse()
        ->and($this->kind->composesBoost($this->project))
        ->toBeTrue();
})->with(['symlink to the shim', 'dangling symlink', 'directory']);

it('sends the shim only where there is no app and Testbench is installed', function (
    array $files,
    array $expected,
): void {
    seedFiles($this->project, $files);

    expect([
        'receives shim' => $this->kind->receivesShim($this->project),
        'has Testbench' => $this->kind->hasTestbench($this->project),
        'composes' => $this->kind->composesBoost($this->project),
    ])->toBe($expected);
})->with([
    'package with Testbench' => [
        [ProjectKind::TESTBENCH => "<?php\n"],
        ['receives shim' => true, 'has Testbench' => true, 'composes' => true],
    ],
    'package holding the shim' => [
        ['artisan' => ProjectKind::SHIM_MARKER . "\n", ProjectKind::TESTBENCH => "<?php\n"],
        ['receives shim' => true, 'has Testbench' => true, 'composes' => true],
    ],
    'package without Testbench' => [
        [],
        ['receives shim' => false, 'has Testbench' => false, 'composes' => false],
    ],
    'app with Testbench' => [
        ['artisan' => "<?php\n", ProjectKind::TESTBENCH => "<?php\n"],
        ['receives shim' => false, 'has Testbench' => true, 'composes' => true],
    ],
]);
