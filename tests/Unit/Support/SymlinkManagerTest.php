<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\SymlinkManager;

it('creates a relative symlink pointing into the package', function (): void {
    $root = makeTempDir();
    $project = $root . '/project';
    $package = $root . '/project/vendor/mikebronner/development-settings';
    mkdir($package . '/.ai/guidelines', 0755, true);
    file_put_contents($package . '/.ai/guidelines/a.md', 'shared');

    $action = (new SymlinkManager)->ensure($project, $package, '.ai', '.ai');

    expect($action)->toBe(SymlinkManager::LINKED)
        ->and(is_link($project . '/.ai'))->toBeTrue()
        ->and(file_get_contents($project . '/.ai/guidelines/a.md'))->toBe('shared');

    removeTempDir($root);
});

it('is idempotent — re-running reports unchanged', function (): void {
    $root = makeTempDir();
    $project = $root . '/project';
    $package = $root . '/pkg';
    mkdir($package . '/.ai', 0755, true);
    file_put_contents($package . '/.ai/x.md', 'x');
    mkdir($project, 0755, true);

    $manager = new SymlinkManager;
    $first = $manager->ensure($project, $package, '.ai', '.ai');
    $second = $manager->ensure($project, $package, '.ai', '.ai');

    expect($first)->toBe(SymlinkManager::LINKED)
        ->and($second)->toBe(SymlinkManager::UNCHANGED);

    removeTempDir($root);
});

it('replaces a previously-copied directory with a symlink', function (): void {
    $root = makeTempDir();
    $project = $root . '/project';
    $package = $root . '/pkg';
    mkdir($package . '/.ai', 0755, true);
    file_put_contents($package . '/.ai/new.md', 'fresh');

    // Simulate a stale real (copied) directory at the link path.
    mkdir($project . '/.ai', 0755, true);
    file_put_contents($project . '/.ai/stale.md', 'old copy');

    $action = (new SymlinkManager)->ensure($project, $package, '.ai', '.ai');

    expect($action)->toBe(SymlinkManager::LINKED)
        ->and(is_link($project . '/.ai'))->toBeTrue()
        ->and(file_exists($project . '/.ai/stale.md'))->toBeFalse()
        ->and(file_exists($project . '/.ai/new.md'))->toBeTrue();

    removeTempDir($root);
});
