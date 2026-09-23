<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\ComposerPlugin;

it('boots the test harness', function (): void {
    expect(true)->toBeTrue();
});

it('autoloads the package namespace', function (): void {
    expect(class_exists(ComposerPlugin::class))->toBeTrue();
});

it('creates and removes a temp directory', function (): void {
    $dir = makeTempDir();

    expect(is_dir($dir))->toBeTrue();

    removeTempDir($dir);

    expect(is_dir($dir))->toBeFalse();
});
