<?php

declare(strict_types=1);

const WRITTEN_BEFORE_THE_RUN = 60;

beforeEach(function (): void {
    $this->read = fn (string $path): string => (string) file_get_contents(
            "{$this->project}/{$path}",
        );
    $this->package = fn (array $options = []): array => makeConsumer([
        'app' => false,
        'testbench' => true,
        'boost' => COMPOSES_WITH_MCP,
        ...$options,
    ]);
});

afterEach(function (): void {
    removeTempDir($this->project);
});

it('roots every MCP config Boost wrote in a package, and checks tool calls', function (
    string $run,
): void {
    [$this->project] = ($this->package)();

    $output = publishIn($this->project, $run);

    expect(($this->read)('.mcp.json'))->toBe(rootedMcpJson())
        ->and(($this->read)('.codex/config.toml'))
        ->toBe(rootedMcpToml())
        ->and(json_decode(($this->read)('mcp-check.ran'), associative: true))
        ->toBe([MCP_CHECK]);
    expect($output)->toContain(
            'Pointed the laravel-boost MCP server in .codex/config.toml, .mcp.json at',
            'Checking Boost MCP tool calls... done',
        );
})->with([NON_INTERACTIVE, INTERACTIVE_ON_A_TERMINAL]);

it('leaves an MCP config Boost did not write during the run alone', function (): void {
    [$this->project] = ($this->package)(['boost' => COMPOSES]);
    seedFiles($this->project, ['.cursor/mcp.json' => BOOST_MCP_JSON]);
    touch("{$this->project}/.cursor/mcp.json", time() - WRITTEN_BEFORE_THE_RUN);

    $output = publishIn($this->project);

    expect(($this->read)('.cursor/mcp.json'))->toBe(BOOST_MCP_JSON);
    expect($output)->not
        ->toContain('Pointed');
});

it("leaves an app's MCP configs and tool calls as Boost wrote them", function (): void {
    [$this->project] = makeConsumer(['testbench' => true, 'boost' => COMPOSES_WITH_MCP]);

    $output = publishIn($this->project);

    expect(($this->read)('.mcp.json'))->toBe(BOOST_MCP_JSON)
        ->and(($this->read)('.codex/config.toml'))
        ->toBe(BOOST_MCP_TOML)
        ->and(file_exists("{$this->project}/mcp-check.ran"))
        ->toBeFalse();
    expect($output)->not
        ->toContain('Pointed')
        ->and($output)
        ->not
        ->toContain('Checking Boost MCP tool calls');
});

it('reports a failed tool call check, and carries on', function (): void {
    [$this->project] = ($this->package)(['mcpCheckExit' => 1]);
    seedFiles($this->project, ['CLAUDE.md' => "@AGENTS.md\n"]);
    $tail = <<<TEXT
        │ MCP check stand-in: <error>ToolExecutor</error> changed
        TEXT;

    $output = publishIn($this->project);

    expect($output)->toContain(
            'Checking Boost MCP tool calls... failed',
            $tail,
            'Deleted CLAUDE.md',
        );
    expect(($this->read)('.mcp.json'))->toBe(rootedMcpJson());
});

it('checks tool calls even when Boost fails', function (): void {
    [$this->project] = ($this->package)(['boost' => EXITS_WITH_ERROR]);

    $output = publishIn($this->project);

    expect($output)->toContain(
            'Laravel Boost exited with an error',
            'Checking Boost MCP tool calls... done',
        );
});

it('checks nothing when Boost was not run', function (): void {
    [$this->project] = ($this->package)();
    seedFiles($this->project, ['boost.json' => '{']);

    $output = publishIn($this->project);

    expect($output)->not
        ->toContain('Checking Boost MCP tool calls')
        ->and(file_exists("{$this->project}/mcp-check.ran"))
        ->toBeFalse();
});
