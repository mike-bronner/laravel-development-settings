<?php

declare(strict_types=1);

beforeEach(function (): void {
    $this->composer = json_decode(
        shippedSource('composer.json'),
        associative: true,
        flags: JSON_THROW_ON_ERROR,
    );
});

/*
 * Pest's parallel runner leaves Laravel's ParallelRunner out whenever
 * Orchestra Testbench's TestCase exists, so every worker of an application
 * shares one database. Required here, Testbench would reach every application,
 * and this repository needs none: its tests stand in for vendor/bin/testbench.
 */
it('requires no orchestra/testbench, for projects or for itself', function (string $list): void {
    expect($this->composer[$list])
        ->not
        ->toHaveKey('orchestra/testbench');
})->with(['require', 'require-dev']);

it('installs the tooling a project needs before the plugin runs', function (string $package): void {
    expect($this->composer['require'])->toHaveKey($package);
})->with([
    'illuminate/collections',
    'larastan/larastan',
    'laravel/boost',
    'laravel/pint',
    'mike-bronner/clean-code',
]);
