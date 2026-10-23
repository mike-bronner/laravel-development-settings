<?php

declare(strict_types=1);

use Composer\IO\BufferIO;
use MikeBronner\DevelopmentSettings\Plugin\AfterBoost;
use MikeBronner\DevelopmentSettings\Plugin\BoostRun;
use MikeBronner\DevelopmentSettings\Support\BoostHooks;
use MikeBronner\DevelopmentSettings\Tests\Fixtures\DetachedTerminal;

beforeEach(function (): void {
    $options = ['app' => false, 'testbench' => true, 'boost' => COMPOSES_WITH_MCP];
    [$this->project] = makeConsumer($options);
    seedFiles($this->project, ['CLAUDE.md' => "@AGENTS.md\n"]);
    $this->output = new BufferIO;
    $this->hooks = new BoostHooks(data_get(consumerConfig($options), 'hooks'));
    $this->boost = new BoostRun($this->output, new DetachedTerminal, $this->hooks, $this->project);
    $this->settle = fn (): mixed => (new AfterBoost($this->output, $this->project, $this->hooks))
        ->settle($this->boost);
});

afterEach(function (): void {
    removeTempDir($this->project);
});

it('roots the MCP configs of the Boost run, then deletes CLAUDE.md', function (): void {
    $this->boost
        ->register();
    $this->boost
        ->run();

    ($this->settle)();

    expect(file_get_contents("{$this->project}/.mcp.json"))->toBe(rootedMcpJson())
        ->and(file_exists("{$this->project}/mcp-check.ran"))
        ->toBeTrue()
        ->and(file_exists("{$this->project}/CLAUDE.md"))
        ->toBeFalse();
});

it('deletes CLAUDE.md, and checks nothing, when Boost did not run', function (): void {
    ($this->settle)();

    expect(file_exists("{$this->project}/mcp-check.ran"))->toBeFalse()
        ->and(file_exists("{$this->project}/CLAUDE.md"))
        ->toBeFalse();
});
