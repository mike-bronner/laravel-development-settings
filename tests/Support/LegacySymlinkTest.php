<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\LegacySymlink;

const PACKAGE_PATH = 'vendor/mike-bronner/laravel-development-settings';

const OLD_PACKAGE_PATH = 'vendor/mikebronner/development-settings';

beforeEach(function (): void {
    $this->root = makeTempDir();
    $this->project = "{$this->root}/project";
    $this->package = "{$this->project}/" . PACKAGE_PATH;
    seedFiles($this->package, ['.ai/guidelines/01.md' => 'shipped']);
});

afterEach(function (): void {
    removeTempDir($this->root);
});

it('removes a link into a package directory, not below it', function (string $target): void {
    symlink(str_replace('{project}', $this->project, $target), "{$this->project}/.ai");
    $symlink = new LegacySymlink([$this->package, "{$this->project}/" . OLD_PACKAGE_PATH]);

    expect($symlink->stale($this->project, ['.ai']))->toBe(['.ai']);

    $symlink->remove($this->project, ['.ai']);

    expect(is_link("{$this->project}/.ai"))->toBeFalse()
        ->and(file_get_contents("{$this->package}/.ai/guidelines/01.md"))
        ->toBe('shipped');
})->with([
    'a relative link' => [PACKAGE_PATH . '/.ai'],
    'an absolute link' => ['{project}/' . PACKAGE_PATH . '/.ai'],
    'a dangling link the upgrade left behind' => [PACKAGE_PATH . '/resources/moved'],
    'a dangling link into the pre-rename path' => [OLD_PACKAGE_PATH . '/.ai'],
]);

it('leaves every other link and directory alone', function (string $link, array $packages): void {
    seedFiles($this->project, [
        PACKAGE_PATH . '-extras/x' => 'x',
        '.ai/guidelines/99-project.md' => 'ours',
    ]);
    symlink($link, "{$this->project}/linked");
    $symlink = new LegacySymlink(collect($packages)
        ->map(fn (string $path): string => "{$this->project}/{$path}")
        ->all());
    $linkPaths = ['.ai', 'linked', 'missing'];
    $stale = $symlink->stale($this->project, $linkPaths);

    $symlink->remove($this->project, $linkPaths);

    expect($stale)->toBe([])
        ->and(is_link("{$this->project}/linked"))
        ->toBeTrue()
        ->and(file_get_contents("{$this->project}/.ai/guidelines/99-project.md"))
        ->toBe('ours');
})->with([
    'a link somewhere else' => ['/', [PACKAGE_PATH]],
    'a link into a vendor sibling' => [PACKAGE_PATH . '-extras', [PACKAGE_PATH]],
    'a link into the old path, not named' => [OLD_PACKAGE_PATH . '/.ai', [PACKAGE_PATH]],
    'a real directory, with the old path named' => ['/', [PACKAGE_PATH, OLD_PACKAGE_PATH]],
]);

it('does nothing when there is no link path to clean up', function (): void {
    expect((new LegacySymlink([$this->package]))->stale($this->project, []))->toBe([]);
});
