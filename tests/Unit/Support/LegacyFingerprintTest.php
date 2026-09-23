<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\LegacyFingerprint;

it('removes a fingerprint file the old runner wrote', function (): void {
    $project = makeTempDir();
    file_put_contents($project . '/.dev-settings-boost', md5('sources') . "\n");

    expect((new LegacyFingerprint)->remove($project))->toBeTrue()
        ->and(file_exists($project . '/.dev-settings-boost'))->toBeFalse();

    removeTempDir($project);
});

it('reports nothing when there is no fingerprint file', function (): void {
    $project = makeTempDir();

    expect((new LegacyFingerprint)->remove($project))->toBeFalse();

    removeTempDir($project);
});

it('keeps a same-named file holding anything but a bare fingerprint', function (): void {
    $project = makeTempDir();
    $content = md5('sources') . "\nproject notes\n";
    file_put_contents($project . '/.dev-settings-boost', $content);

    expect((new LegacyFingerprint)->remove($project))->toBeFalse()
        ->and(file_get_contents($project . '/.dev-settings-boost'))->toBe($content);

    removeTempDir($project);
});

it('keeps a same-named directory', function (): void {
    $project = makeTempDir();
    mkdir($project . '/.dev-settings-boost');

    expect((new LegacyFingerprint)->remove($project))->toBeFalse()
        ->and(is_dir($project . '/.dev-settings-boost'))->toBeTrue();

    removeTempDir($project);
});

it('does not remove a symlink, even to a fingerprint', function (): void {
    $project = makeTempDir();
    file_put_contents($project . '/elsewhere', md5('sources'));
    symlink('elsewhere', $project . '/.dev-settings-boost');

    expect((new LegacyFingerprint)->remove($project))->toBeFalse()
        ->and(is_link($project . '/.dev-settings-boost'))->toBeTrue();

    removeTempDir($project);
});
