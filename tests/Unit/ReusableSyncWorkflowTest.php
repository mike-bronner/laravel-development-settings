<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\InstalledPackage;
use MikeBronner\DevelopmentSettings\Support\SystemProcess;

const SELF_CALL_GUARD = <<<REGEX
    /^  sync:\n(?:    #.*\n)*    if: github\.repository != '([^']+)'\n/m
    REGEX;

const MIN_FENCE = 3;

const CLOSING_FENCE = '/^ {0,3}`{%d,}[ \t]*$/';

afterEach(function (): void {
    removeTempDir($this->project);
});

/*
 * The package ships the calling workflow, so it runs the reverse sync on
 * itself. Its root .gitignore differs from the shipped one on purpose, and a
 * run here proposed the root copy over the shipped source (PR #32). The guard
 * sits on the job, directly under its key, so every step is skipped.
 */
it('skips the reverse sync when the package itself is the caller', function (): void {
    $this->project = makeTempDir();

    preg_match(SELF_CALL_GUARD, (string) file_get_contents(REUSABLE_SYNC), $guard);

    expect(data_get($guard, 1))->toBe(InstalledPackage::NAME);
});

/*
 * The workflow's PHP step requires every class it uses, from the package
 * checkout, and writes only the managed section.
 */
it('copies only the managed section of the .gitignore into the package', function (): void {
    [$this->project, $package, $shipped] = syncFixture();

    $result = runSyncStep($this->project);

    expect($result->exitCode())->toBe(0, $result->output());
    expect(file_get_contents("{$package}/resources/project/gitignore"))
        ->toBe("{$shipped}/edited\n");
    $proposed = json_encode('.gitignore') . ' -> ' . json_encode('resources/project/gitignore');

    expect($result->output())->toContain($proposed);
});

it('fails the step when a changed file cannot be written to the package', function (): void {
    [$this->project, $package, $shipped] = syncFixture();
    chmod("{$package}/resources/project/gitignore", MODE_READ_ONLY);

    $result = runSyncStep($this->project);
    chmod("{$package}/resources/project/gitignore", MODE_WRITABLE);

    expect($result->exitCode())->toBe(1, $result->output());
    expect($result->output())
        ->toContain('Could not write _laravel-development-settings/resources/project/gitignore: ');
    expect(str_contains($result->output(), '->'))->toBeFalse();
    expect(file_get_contents("{$package}/resources/project/gitignore"))->toBe($shipped);
});

/*
 * File names are project input, so each changed one must stay inside the
 * code fence of the PR body the change detection feeds.
 */
it('keeps every changed file name inside the code fence of the PR body', function (): void {
    $this->project = makeTempDir('devset-detect-');
    $package = "{$this->project}/_laravel-development-settings";
    $backticks = str_repeat('`', MIN_FENCE);
    seedFiles($package, ['pint.json' => 'shipped']);
    (new SystemProcess())->run('git init -q && git add pint.json', $package);
    seedFiles($package, [
        'pint.json' => 'edited',
        "a{$backticks}b.txt" => 'x',
        "{$backticks}`" => 'x',
    ]);

    $lines = explode("\n", renderPullRequestBody(runDetectStep($this->project)));
    $open = (int) array_search('**Changed files:**', $lines, strict: true) + 1;
    $fence = (string) data_get($lines, $open);
    $closing = sprintf(CLOSING_FENCE, strlen($fence));
    $fenced = collect(array_slice($lines, $open + 1))
        ->takeUntil(fn (string $line): bool => preg_match($closing, $line) === 1)
        ->all();

    expect($fence)->toMatch('/^`{3,}$/');
    expect($fenced)->toBe(["{$backticks}`", "a{$backticks}b.txt", 'pint.json']);
});
