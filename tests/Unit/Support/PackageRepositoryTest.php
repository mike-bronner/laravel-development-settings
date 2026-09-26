<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\PackageRepository;

function repositoryWithArtisan(?string $artisan): string
{
    $project = makeTempDir('devset-repository-');

    if ($artisan !== null) {
        file_put_contents($project . '/artisan', $artisan);
    }

    return $project;
}

it('tells an app by an artisan that carries no shim marker', function (?string $artisan, bool $isApp): void {
    $project = repositoryWithArtisan($artisan);

    expect(PackageRepository::isApp($project))->toBe($isApp);

    removeTempDir($project);
})->with([
    'no artisan' => [null, false],
    'an app artisan' => ["#!/usr/bin/env php\n<?php\n", true],
    'the shim' => ["#!/usr/bin/env php\n<?php\n\n" . PackageRepository::SHIM_MARKER . "\n", false],
    'the shim with CRLF endings' => ["#!/usr/bin/env php\r\n" . PackageRepository::SHIM_MARKER . "\r\n", false],
    'the marker inside a longer line' => ['// See: ' . PackageRepository::SHIM_MARKER . "\n", true],
]);

it('treats an artisan it cannot read as the shim as an app, which is never touched', function (string $shape): void {
    $project = makeTempDir('devset-repository-');
    $shim = $project . '/elsewhere';
    file_put_contents($shim, PackageRepository::SHIM_MARKER . "\n");

    match ($shape) {
        'symlink to the shim' => symlink($shim, $project . '/artisan'),
        'dangling symlink' => symlink($project . '/missing', $project . '/artisan'),
        'directory' => mkdir($project . '/artisan'),
    };

    expect(PackageRepository::isApp($project))->toBeTrue()
        ->and(PackageRepository::receivesShim($project))->toBeFalse();

    removeTempDir($project);
})->with(['symlink to the shim', 'dangling symlink', 'directory']);

it('sends the shim only to a repository with no artisan of its own and with Testbench installed', function (?string $artisan, bool $testbench, bool $receives): void {
    $project = repositoryWithArtisan($artisan);

    if ($testbench) {
        mkdir($project . '/vendor/bin', 0755, true);
        file_put_contents($project . '/' . PackageRepository::TESTBENCH, "<?php\n");
    }

    expect(PackageRepository::receivesShim($project))->toBe($receives)
        ->and(PackageRepository::hasTestbench($project))->toBe($testbench);

    removeTempDir($project);
})->with([
    'package with Testbench' => [null, true, true],
    'package holding the shim' => [PackageRepository::SHIM_MARKER . "\n", true, true],
    'package without Testbench' => [null, false, false],
    'app with Testbench' => ["<?php\n", true, false],
]);
