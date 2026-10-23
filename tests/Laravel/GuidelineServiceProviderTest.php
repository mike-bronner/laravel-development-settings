<?php

declare(strict_types=1);

use Illuminate\Console\Application;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Laravel\Boost\Mcp\ToolExecutor;
use MikeBronner\DevelopmentSettings\Laravel\GuidelineServiceProvider;
use MikeBronner\DevelopmentSettings\Laravel\McpExecutorCheck;
use MikeBronner\DevelopmentSettings\Laravel\RootedToolExecutor;
use MikeBronner\DevelopmentSettings\Support\RootedTestbench;
use MikeBronner\DevelopmentSettings\Tests\Fixtures\ArrayConfig;
use MikeBronner\DevelopmentSettings\Tests\Fixtures\RootedApp;

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

describe('in a repository without artisan', function (): void {
    beforeEach(function (): void {
        $this->project = makeTempDir('devset-provider-');
        $this->app = new RootedApp($this->project);
        Container::setInstance($this->app);
        $this->register = function (array $files): void {
            seedFiles($this->project, $files);
            (new GuidelineServiceProvider($this->app))->register();
        };
        $this->script = RootedTestbench::SCRIPT;
    });

    afterEach(function (): void {
        Container::setInstance(null);
        Application::forgetBootstrappers();
        removeTempDir($this->project);
    });

    it("runs Boost's tool calls through the rooted script", function (): void {
        ($this->register)([$this->script => '<?php']);

        $executor = $this->app
            ->make(ToolExecutor::class);

        expect($executor)->toBeInstanceOf(RootedToolExecutor::class)
            ->and($executor->probe())
            ->toContain("{$this->project}/{$this->script}");
    });

    it('registers the check the plugin runs', function (): void {
        ($this->register)([RootedTestbench::SCRIPT => '<?php']);

        $artisan = new Application($this->app, new Dispatcher($this->app), 'testing');

        expect($artisan->has(McpExecutorCheck::NAME))->toBeTrue();
    });

    it("leaves Boost's executor and commands alone", function (array $files): void {
        ($this->register)($files);

        $artisan = new Application($this->app, new Dispatcher($this->app), 'testing');

        expect($this->app->make(ToolExecutor::class))->not
            ->toBeInstanceOf(RootedToolExecutor::class)
            ->and($artisan->has(McpExecutorCheck::NAME))
            ->toBeFalse();
    })->with([
        'an app, whose artisan is real' => [
            ['artisan' => '<?php', RootedTestbench::SCRIPT => '<?php'],
        ],
        'no rooted script in vendor' => [[]],
    ]);
});
