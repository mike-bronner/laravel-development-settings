<?php

declare(strict_types=1);

use Composer\IO\BufferIO;
use MikeBronner\DevelopmentSettings\Plugin\IgnoreOverrideNotice;

beforeEach(function (): void {
    $this->project = makeTempDir('devset-override-notice-');
    $this->gitignore = "{$this->project}/.gitignore";
    $this->output = new BufferIO;
    seedFiles($this->project, ['shipped/gitignore' => "/vendor\n"]);
    $this->synced = ['.gitignore' => "{$this->project}/shipped/gitignore"];
    $this->warn = function (array $files): string {
        (new IgnoreOverrideNotice($this->output, $this->project))->warn($files);

        return $this->output
            ->getOutput();
    };
});

afterEach(function (): void {
    removeTempDir($this->project);
});

it('lists each overriding line with its number and reason, and keeps it', function (): void {
    $gitignore = marked("/vendor\n", "\n.ai\n/vendor\n");
    file_put_contents($this->gitignore, $gitignore);

    $output = ($this->warn)($this->synced);

    expect($output)->toContain(
            '.gitignore: these lines below the sync marker override the shipped rules above it',
            'line 3: .ai (ignores .ai/, which the shipped rules leave tracked)',
            'line 4: /vendor (repeats a shipped rule)',
            'Delete each one you did not mean to keep.',
        );
    expect(file_get_contents($this->gitignore))->toBe($gitignore);
});

it('prints a line as text, not as a style', function (): void {
    file_put_contents($this->gitignore, marked("/vendor\n", "\nAGENTS.m[<info>d]\n"));

    expect(($this->warn)($this->synced))
        ->toContain('line 3: AGENTS.m[<info>d] (ignores AGENTS.md');
});

it('says nothing when nothing below the marker overrides', function (string $gitignore): void {
    file_put_contents($this->gitignore, $gitignore);

    expect(($this->warn)($this->synced))->toBe('');
})->with([
    'project rules only' => [marked("/vendor\n", "\n/deprecations.log\n")],
    'no marker' => ["/vendor\n.ai\n/vendor\n"],
]);

it('says nothing when .gitignore is missing, or not a file it can read', function (
    string $shape,
): void {
    match ($shape) {
        'missing' => null,
        'symlink' => symlink("{$this->project}/shipped/gitignore", $this->gitignore),
        'directory' => mkdir($this->gitignore),
    };

    expect(($this->warn)($this->synced))->toBe('');
})->with(['missing', 'symlink', 'directory']);

it('says nothing when .gitignore is not a synced file', function (): void {
    file_put_contents($this->gitignore, marked("/vendor\n", "\n/vendor\n"));

    expect(($this->warn)(['pint.json' => "{$this->project}/shipped/gitignore"]))->toBe('');
});
