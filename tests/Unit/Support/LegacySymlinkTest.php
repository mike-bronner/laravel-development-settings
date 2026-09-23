<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\LegacySymlink;

/**
 * @return array{0: string, 1: string} project dir, package dir
 */
function makeUpgradeFixture(): array
{
    $root = makeTempDir();

    mkdir($root . '/project/vendor/mikebronner/development-settings', 0755, true);

    return [$root . '/project', $root . '/project/vendor/mikebronner/development-settings'];
}

it('removes a relative link pointing into the package', function (): void {
    [$project, $package] = makeUpgradeFixture();
    mkdir($package . '/.ai', 0755, true);
    symlink('vendor/mikebronner/development-settings/.ai', $project . '/.ai');

    $removed = (new LegacySymlink)->remove($project, $package, ['.ai']);

    expect($removed)->toBe(['.ai'])
        ->and(file_exists($project . '/.ai'))->toBeFalse()
        ->and(is_dir($package . '/.ai'))->toBeTrue();

    removeTempDir(dirname($project));
});

it('removes a dangling link whose target the upgrade already deleted', function (): void {
    [$project, $package] = makeUpgradeFixture();

    // The release that moves sources to resources/boost leaves the old link
    // pointing at nothing. This is the case that breaks composition outright.
    symlink('vendor/mikebronner/development-settings/.ai', $project . '/.ai');

    expect(is_dir($project . '/.ai'))->toBeFalse();

    $removed = (new LegacySymlink)->remove($project, $package, ['.ai']);

    expect($removed)->toBe(['.ai'])
        ->and(is_link($project . '/.ai'))->toBeFalse();

    removeTempDir(dirname($project));
});

it('removes an absolute link pointing into the package', function (): void {
    [$project, $package] = makeUpgradeFixture();
    mkdir($package . '/.ai', 0755, true);
    symlink($package . '/.ai', $project . '/.ai');

    expect((new LegacySymlink)->remove($project, $package, ['.ai']))->toBe(['.ai']);

    removeTempDir(dirname($project));
});

it('leaves a real directory alone, because the project now owns it', function (): void {
    [$project, $package] = makeUpgradeFixture();
    mkdir($project . '/.ai/guidelines', 0755, true);
    file_put_contents($project . '/.ai/guidelines/99-project.md', 'ours');

    $removed = (new LegacySymlink)->remove($project, $package, ['.ai']);

    expect($removed)->toBe([])
        ->and(file_get_contents($project . '/.ai/guidelines/99-project.md'))->toBe('ours');

    removeTempDir(dirname($project));
});

it('leaves a link pointing somewhere other than the package alone', function (): void {
    [$project, $package] = makeUpgradeFixture();
    $elsewhere = makeTempDir();
    symlink($elsewhere, $project . '/.ai');

    expect((new LegacySymlink)->remove($project, $package, ['.ai']))->toBe([])
        ->and(is_link($project . '/.ai'))->toBeTrue();

    removeTempDir(dirname($project));
    removeTempDir($elsewhere);
});

it('leaves a link pointing at a vendor sibling alone', function (): void {
    [$project, $package] = makeUpgradeFixture();
    mkdir($project . '/vendor/mikebronner/development-settings-extras', 0755, true);

    // A prefix test rather than a path test would match this: the sibling's
    // name begins with the package directory's full name.
    symlink('vendor/mikebronner/development-settings-extras', $project . '/.ai');

    expect((new LegacySymlink)->remove($project, $package, ['.ai']))->toBe([])
        ->and(is_link($project . '/.ai'))->toBeTrue();

    removeTempDir(dirname($project));
});

it('does nothing when there is no link path to clean up', function (): void {
    [$project, $package] = makeUpgradeFixture();

    expect((new LegacySymlink)->remove($project, $package, []))->toBe([])
        ->and((new LegacySymlink)->remove($project, $package, ['.ai']))->toBe([]);

    removeTempDir(dirname($project));
});
