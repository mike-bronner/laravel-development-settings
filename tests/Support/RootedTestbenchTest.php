<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\RootedTestbench;

beforeEach(function (): void {
    $this->project = makeTempDir('devset-rooted-mcp-');
    $this->rooted = new RootedTestbench($this->project);
});

afterEach(function (): void {
    removeTempDir($this->project);
});

it('names the rooted script in vendor', function (): void {
    expect(RootedTestbench::SCRIPT)
        ->toBe('vendor/mike-bronner/laravel-development-settings/bin/rooted-testbench.php')
        ->and(RootedTestbench::MCP_SERVER)
        ->toBe('laravel-boost');
});

it('replaces a relative artisan with the relative script', function (): void {
    expect($this->rooted->replacement('artisan'))->toBe(RootedTestbench::SCRIPT);
});

it("replaces the project's absolute artisan with the absolute script", function (): void {
    expect($this->rooted->replacement("{$this->project}/artisan"))
        ->toBe("{$this->project}/" . RootedTestbench::SCRIPT);
});

it('replaces an absolute artisan reached through a link to the project', function (): void {
    $link = makeTempDir('devset-rooted-link-') . '/project';
    symlink($this->project, $link);

    try {
        expect($this->rooted->replacement("{$link}/artisan"))
            ->toBe("{$link}/" . RootedTestbench::SCRIPT);
    } finally {
        removeTempDir(dirname($link));
    }
});

it('leaves every other argument alone', function (string $argument): void {
    expect($this->rooted->replacement($argument))->toBeNull();
})->with([
    'the command' => ['boost:mcp'],
    'a file named like artisan' => ['my-artisan'],
    'artisan in a subdirectory' => ['bin/artisan'],
    'another project\'s artisan' => ['/elsewhere/artisan'],
    'an empty argument' => [''],
]);

it('recognizes the script, relative or absolute', function (): void {
    expect($this->rooted->isScript(RootedTestbench::SCRIPT))->toBeTrue()
        ->and($this->rooted->isScript('/project/' . RootedTestbench::SCRIPT))
        ->toBeTrue()
        ->and($this->rooted->isScript('artisan'))
        ->toBeFalse();
});
