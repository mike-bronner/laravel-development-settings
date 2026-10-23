<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use MikeBronner\DevelopmentSettings\Laravel\RootedToolExecutor;
use MikeBronner\DevelopmentSettings\Support\RootedTestbench;
use MikeBronner\DevelopmentSettings\Tests\Fixtures\RootedApp;

beforeEach(function (): void {
    Container::setInstance(new RootedApp('/repo'));
    $this->script = '/repo/' . RootedTestbench::SCRIPT;
});

afterEach(function (): void {
    Container::setInstance(null);
});

it("runs Boost's tool command through the script in place of artisan", function (): void {
    $probe = (new RootedToolExecutor('/repo/artisan', $this->script))->probe();

    expect($probe)->toBe([
        PHP_BINARY,
        $this->script,
        'boost:execute-tool',
        RootedToolExecutor::class,
        base64_encode('[]'),
    ]);
});

it('fails, naming the command, when Boost no longer runs tools through artisan', function (): void {
    $executor = new RootedToolExecutor('/elsewhere/artisan', $this->script);

    expect(fn (): array => $executor->probe())->toThrow(
            RuntimeException::class,
            'Laravel Boost no longer runs its tools through /elsewhere/artisan, so this package'
                . " cannot run them through {$this->script} instead. Its command was: "
                . PHP_BINARY . ' /repo/artisan boost:execute-tool',
        );
});
