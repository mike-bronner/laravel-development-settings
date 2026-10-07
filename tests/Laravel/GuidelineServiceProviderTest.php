<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use MikeBronner\DevelopmentSettings\Laravel\GuidelineServiceProvider;
use MikeBronner\DevelopmentSettings\Tests\Fixtures\ArrayConfig;

beforeEach(function (): void {
    $this->boot = function (array $config): ArrayConfig {
        $app = new Container;
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

it('points Claude Code\'s guidelines at AGENTS.md when the app sets no path', function (
    array $config,
): void {
    expect(($this->boot)($config)->get('boost.agents.claude_code.guidelines_path'))
        ->toBe('AGENTS.md');
})->with([
    'no boost config' => [[]],
    'no Claude Code config' => [
        ['boost' => ['agents' => ['cursor' => ['guidelines_path' => 'X.md']]]],
    ],
    'an empty path, which Boost treats as unset' => [
        ['boost' => ['agents' => ['claude_code' => ['guidelines_path' => '']]]],
    ],
    'a null path' => [['boost' => ['agents' => ['claude_code' => ['guidelines_path' => null]]]]],
]);

it('keeps a Claude Code guidelines path the app set', function (): void {
    $config = ['boost' => ['agents' => ['claude_code' => [
        'guidelines_path' => 'CLAUDE.md',
        'skills_path' => '.claude/skills',
    ]]]];

    expect(data_get(($this->boot)($config)->all(), 'boost.agents'))->toBe([
        'claude_code' => ['guidelines_path' => 'CLAUDE.md', 'skills_path' => '.claude/skills'],
    ]);
});

it('leaves other agents\' config alone', function (): void {
    $config = ['boost' => ['agents' => ['cursor' => ['guidelines_path' => 'X.md']]]];

    expect(data_get(($this->boot)($config)->all(), 'boost.agents.cursor'))
        ->toBe(['guidelines_path' => 'X.md']);
});
