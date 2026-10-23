<?php

declare(strict_types=1);

use Illuminate\Console\OutputStyle;
use Illuminate\Container\Container;
use Laravel\Boost\Mcp\ToolExecutor;
use MikeBronner\DevelopmentSettings\Laravel\McpExecutorCheck;
use MikeBronner\DevelopmentSettings\Laravel\RootedToolExecutor;
use MikeBronner\DevelopmentSettings\Support\BoostHooks;
use MikeBronner\DevelopmentSettings\Support\RootedTestbench;
use MikeBronner\DevelopmentSettings\Tests\Fixtures\RootedApp;
use MikeBronner\DevelopmentSettings\Tests\Fixtures\UninjectedCaller;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

beforeEach(function (): void {
    $this->app = new RootedApp('/repo');
    Container::setInstance($this->app);
    $this->script = '/repo/' . RootedTestbench::SCRIPT;
    $this->bind = function (string $artisan): void {
        $executor = new RootedToolExecutor($artisan, $this->script);
        $this->app
            ->instance(ToolExecutor::class, $executor);
    };
    $this->check = function (McpExecutorCheck $check = new McpExecutorCheck): array {
        $output = new BufferedOutput;
        $check->setLaravel($this->app);
        $check->setOutput(new OutputStyle(new ArrayInput([]), $output));
        $exitCode = $check->handle();

        return [$exitCode, $output->fetch()];
    };
});

afterEach(function (): void {
    Container::setInstance(null);
});

it('passes, and prints the tool command, when tools run through the script', function (): void {
    ($this->bind)('/repo/artisan');

    [$exitCode, $output] = ($this->check)();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($output)
        ->toContain("{$this->script} boost:execute-tool")
        ->not
        ->toContain('/repo/artisan');
});

it("fails when the container resolves Boost's own executor", function (): void {
    [$exitCode, $output] = ($this->check)();

    expect($exitCode)->toBe(Command::FAILURE)
        ->and($output)
        ->toContain(
            "The container resolves Boost's tool executor to " . ToolExecutor::class
                . ', not to ' . RootedToolExecutor::class,
        );
});

it('fails when Boost no longer runs tools through artisan', function (): void {
    ($this->bind)('/elsewhere/artisan');

    [$exitCode, $output] = ($this->check)();

    expect($exitCode)->toBe(Command::FAILURE)
        ->and($output)
        ->toContain('Laravel Boost no longer runs its tools through /elsewhere/artisan');
});

it('fails when a class it relies on is gone', function (string $executor, string $caller): void {
    [$exitCode, $output] = ($this->check)(new McpExecutorCheck($executor, $caller));

    expect($exitCode)->toBe(Command::FAILURE)
        ->and($output)
        ->toContain('Laravel Boost has no Gone\\Executor class.');
})->with([
    'the executor' => ['Gone\\Executor', UninjectedCaller::class],
    'the caller' => [ToolExecutor::class, 'Gone\\Executor'],
]);

it('fails when the tool call no longer receives the executor', function (): void {
    ($this->bind)('/repo/artisan');

    [$exitCode, $output] = ($this->check)(
            new McpExecutorCheck(ToolExecutor::class, UninjectedCaller::class),
        );

    expect($exitCode)->toBe(Command::FAILURE)
        ->and($output)
        ->toContain(UninjectedCaller::class . ' no longer receives ' . ToolExecutor::class);
});

it('is hidden, under the name the plugin runs', function (): void {
    $check = new McpExecutorCheck;

    expect($check->getName())->toBe('dev-settings:mcp-executor')
        ->and($check->isHidden())
        ->toBeTrue()
        ->and(BoostHooks::MCP_CHECK_COMMAND)
        ->toEndWith(' ' . McpExecutorCheck::NAME);
});
