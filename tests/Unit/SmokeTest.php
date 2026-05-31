<?php

declare(strict_types=1);

it('boots the test harness', function (): void {
    expect(true)->toBeTrue();
});

it('autoloads the package namespace', function (): void {
    expect(class_exists(\MikeBronner\DevelopmentSettings\ComposerPlugin::class))->toBeTrue();
});

it('creates and removes a temp directory', function (): void {
    $dir = makeTempDir();

    expect(is_dir($dir))->toBeTrue();

    removeTempDir($dir);

    expect(is_dir($dir))->toBeFalse();
});
