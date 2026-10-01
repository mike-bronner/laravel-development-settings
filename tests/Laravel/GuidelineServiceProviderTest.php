<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use MikeBronner\DevelopmentSettings\Laravel\GuidelineServiceProvider;
use MikeBronner\DevelopmentSettings\Tests\Fixtures\ArrayConfig;

beforeEach(function (): void {
    $this->boot = function (array $config): ArrayConfig {
        $app = new Container();
        $repository = new ArrayConfig($config);
        $app->instance('config', $repository);
        (new GuidelineServiceProvider($app))->boot();

        return $repository;
    };
    $this->excluded = fn (array $config): mixed => ($this->boot)($config)
        ->get('boost.guidelines.exclude');
});

it('excludes Boost\'s php guideline when the app excludes nothing', function (array $config): void {
    expect(($this->excluded)($config))->toBe(['php']);
})->with([
    'no boost config' => [[]],
    'an empty exclusion list' => [['boost' => ['guidelines' => ['exclude' => []]]]],
]);

it('keeps every exclusion the app already lists, and adds php after them', function (): void {
    $config = ['boost' => ['guidelines' => ['exclude' => ['laravel/style', 'herd']]]];

    expect(($this->excluded)($config))->toBe(['laravel/style', 'herd', 'php']);
});

it('adds php once when the app already excludes it', function (): void {
    $config = ['boost' => ['guidelines' => ['exclude' => ['php', 'herd']]]];

    expect(($this->excluded)($config))->toBe(['php', 'herd']);
});

it('leaves the rest of the Boost config alone', function (): void {
    $config = ['boost' => ['enabled' => true, 'guidelines' => ['exclude' => []]]];

    expect(($this->boot)($config)->get('boost.enabled'))->toBeTrue();
});
