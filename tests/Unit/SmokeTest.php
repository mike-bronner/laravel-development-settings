<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\ComposerPlugin;

it('autoloads the package namespace', function (): void {
    expect(class_exists(ComposerPlugin::class))->toBeTrue();
});

it('creates and removes a temp directory', function (): void {
    $dir = makeTempDir();
    $created = is_dir($dir);

    removeTempDir($dir);

    expect([$created, is_dir($dir)])->toBe([true, false]);
});
