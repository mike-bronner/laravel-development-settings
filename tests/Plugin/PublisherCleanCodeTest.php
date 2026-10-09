<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\InstalledPackage;

const CLEAN_CODE = 'mike-bronner/clean-code';

const CLEAN_CODE_NOTICE = 'This project does not require mike-bronner/clean-code in its own'
    . ' composer.json, so Laravel Boost composes none of its guidelines.'
    . " Run \"composer require --dev mike-bronner/clean-code\".";

const EVERY_RUN = [NON_INTERACTIVE, INTERACTIVE, INTERACTIVE_ON_A_TERMINAL];

afterEach(function (): void {
    removeTempDir($this->project);
});

it('tells a project that does not require clean-code to, and lists only itself', function (
    array $options,
    string $run,
): void {
    [$this->project] = makeConsumer(['voice' => SILENT, ...$options]);
    requireInProject($this->project, ['require' => ['acme/other' => '^1.0']]);

    $output = publishIn($this->project, $run);

    expect($output)->toContain(CLEAN_CODE_NOTICE);
    expect(data_get(boostConfigIn($this->project), 'packages'))->toBe([InstalledPackage::NAME]);
})->with([
    'an app' => [[]],
    'a package repository' => [['app' => false, 'testbench' => true]],
])->with(EVERY_RUN);

it('registers clean-code, and says nothing, where the project requires it', function (
    array $options,
    string $key,
    string $name,
    string $run,
): void {
    [$this->project] = makeConsumer(['voice' => SILENT, ...$options]);
    requireInProject($this->project, [$key => [$name => '^0.2']]);

    $output = publishIn($this->project, $run);

    expect($output)->not
        ->toContain(CLEAN_CODE);
    expect(data_get(boostConfigIn($this->project), 'packages'))
        ->toBe([InstalledPackage::NAME, CLEAN_CODE]);
})->with([
    'an app, in require' => [[], 'require', CLEAN_CODE],
    'an app, in require-dev' => [[], 'require-dev', CLEAN_CODE],
    'a package repository, in require-dev' => [
        ['app' => false, 'testbench' => true],
        'require-dev',
        CLEAN_CODE,
    ],
])->with(EVERY_RUN);

it('tells the project even when Boost does not run', function (): void {
    [$this->project] = makeConsumer(['app' => false]);

    $output = publishIn($this->project);

    expect($output)->toContain(CLEAN_CODE_NOTICE, 'so Laravel Boost was not run');
    expect(file_exists("{$this->project}/boost.json"))->toBeFalse();
});

it('registers clean-code nowhere Boost cannot compose, even when required', function (): void {
    [$this->project] = makeConsumer(['app' => false]);
    requireInProject($this->project, ['require-dev' => [CLEAN_CODE => '^0.2']]);

    $output = publishIn($this->project);

    expect($output)->not
        ->toContain(CLEAN_CODE);
    expect(file_exists("{$this->project}/boost.json"))->toBeFalse();
});
