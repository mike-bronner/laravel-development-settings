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

describe('in a package repository that requires a Flare client', function (): void {
    beforeEach(function (): void {
        $this->settleWith = function (int $flareExit): string {
            $options = [
                'app' => false,
                'testbench' => true,
                'boost' => COMPOSES_WITH_MCP,
                'flareExit' => $flareExit,
            ];
            removeTempDir($this->project);
            [$this->project] = makeConsumer($options);
            seedFiles($this->project, [
                'CLAUDE.md' => "@AGENTS.md\n",
                'vendor/spatie/laravel-flare/composer.json' => '{}',
            ]);
            $output = new BufferIO;
            $hooks = new BoostHooks(data_get(consumerConfig($options), 'hooks'));
            $boost = new BoostRun($output, new DetachedTerminal, $hooks, $this->project);
            $boost->register();
            $boost->run();
            (new AfterBoost($output, $this->project, $hooks))->settle($boost);

            return $output->getOutput();
        };
    });

    it('roots laravel-boost as before, then adds the Flare server beside it', function (): void {
        $output = ($this->settleWith)(0);
        $servers = data_get(
                json_decode((string) file_get_contents("{$this->project}/.mcp.json"), true),
                'mcpServers',
            );

        expect($servers)->toBe([
            'laravel-boost' => json_decode(rootedMcpJson(), true)['mcpServers']['laravel-boost'],
            'flare' => ['type' => 'http', 'url' => 'https://flareapp.io/mcp'],
        ])
            ->and(file_get_contents("{$this->project}/.codex/config.toml"))
            ->toBe(rootedMcpToml())
            ->and(json_decode((string) file_get_contents("{$this->project}/flare.ran"), true))
            ->toBe([FLARE_MCP])
            ->and($output)
            ->toContain(
                'Pointed the laravel-boost MCP server in .codex/config.toml, .mcp.json at',
                'Checking Boost MCP tool calls... done',
                'Adding the Flare MCP server... done',
            );
    });

    it('reports a failed Flare step, and leaves the rest of the run alone', function (): void {
        $output = ($this->settleWith)(1);

        expect($output)->toContain(
                'Composing Laravel Boost guidelines and skills... done',
                'Checking Boost MCP tool calls... done',
                'Adding the Flare MCP server... failed',
                "Run \"" . BoostHooks::TESTBENCH_FLARE_MCP_COMMAND . "\" to see why.",
                <<<TEXT
                        │ Flare stand-in: <error>installHttpMcp</error> failed
                    TEXT,
            )
            ->and(file_exists("{$this->project}/CLAUDE.md"))
            ->toBeFalse();
    });
});

it('runs no Flare step, and says nothing of Flare, without a Flare client', function (): void {
    $this->boost
        ->register();
    $this->boost
        ->run();

    ($this->settle)();

    expect(file_exists("{$this->project}/flare.ran"))->toBeFalse()
        ->and($this->output->getOutput())
        ->not
        ->toContain('Flare');
});
