<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\McpConfigRewriter;

beforeEach(function (): void {
    $this->project = makeTempDir('devset-mcp-rewriter-');
    $this->startedAt = time();
    $this->rewrite = fn (): array => (new McpConfigRewriter)->rewrite(
            $this->project,
            $this->startedAt,
        );
    $this->configs = fn (): array => data_get(($this->rewrite)(), 'configs');
    $this->read = fn (string $path): string => (string) file_get_contents(
            "{$this->project}/{$path}",
        );
});

afterEach(function (): void {
    removeTempDir($this->project);
});

it('roots every config written during the run, wherever it sits', function (): void {
    seedFiles($this->project, [
        '.mcp.json' => BOOST_MCP_JSON,
        'custom/agent/mcp.jsonc' => BOOST_MCP_JSON,
        '.codex/config.toml' => BOOST_MCP_TOML,
        'AGENTS.md' => agentBlock(),
    ]);

    expect(($this->configs)())->toBe([
        '.codex/config.toml' => null,
        '.mcp.json' => null,
        'custom/agent/mcp.jsonc' => null,
    ]);
    expect(($this->read)('.mcp.json'))->toBe(rootedMcpJson())
        ->and(($this->read)('custom/agent/mcp.jsonc'))
        ->toBe(rootedMcpJson())
        ->and(($this->read)('.codex/config.toml'))
        ->toBe(rootedMcpToml())
        ->and(($this->read)('AGENTS.md'))
        ->toBe(agentBlock());
});

it('reads a file by its extension, as Boost writes it', function (): void {
    seedFiles($this->project, ['mcp.toml' => BOOST_MCP_JSON, 'mcp.json' => BOOST_MCP_TOML]);

    expect(($this->rewrite)())->toBe(NOTHING_REWRITTEN);
    expect(($this->read)('mcp.toml'))->toBe(BOOST_MCP_JSON)
        ->and(($this->read)('mcp.json'))
        ->toBe(BOOST_MCP_TOML);
});

it('leaves a config written before the run alone', function (): void {
    seedFiles($this->project, ['.mcp.json' => BOOST_MCP_JSON]);
    touch("{$this->project}/.mcp.json", $this->startedAt - 1);

    expect(($this->rewrite)())->toBe(NOTHING_REWRITTEN);
    expect(($this->read)('.mcp.json'))->toBe(BOOST_MCP_JSON);
});

it('leaves configs in vendor alone', function (): void {
    seedFiles($this->project, ['vendor/acme/tool/.mcp.json' => BOOST_MCP_JSON]);

    expect(($this->rewrite)())->toBe(NOTHING_REWRITTEN);
    expect(($this->read)('vendor/acme/tool/.mcp.json'))->toBe(BOOST_MCP_JSON);
});

it('neither writes nor lists a config that is already rooted', function (): void {
    seedFiles($this->project, [
        '.mcp.json' => rootedMcpJson(),
        '.codex/config.toml' => rootedMcpToml(),
        '.cursor/mcp.json' => BOOST_MCP_JSON,
    ]);
    chmod("{$this->project}/.mcp.json", MODE_READ_ONLY);
    chmod("{$this->project}/.codex/config.toml", MODE_READ_ONLY);

    expect(($this->configs)())->toBe(['.cursor/mcp.json' => null]);
});

it('reports a config it cannot write, with the cause, and roots the rest', function (): void {
    seedFiles($this->project, [
        '.mcp.json' => BOOST_MCP_JSON,
        '.cursor/mcp.json' => BOOST_MCP_JSON,
    ]);
    chmod("{$this->project}/.mcp.json", MODE_READ_ONLY);

    $outcomes = collect(($this->configs)());

    expect($outcomes->get('.mcp.json'))
        ->toStartWith("Could not write {$this->project}/.mcp.json")
        ->and($outcomes->keys()->all())
        ->toBe(['.cursor/mcp.json', '.mcp.json'])
        ->and($outcomes->get('.cursor/mcp.json'))
        ->toBeNull();
    expect(($this->read)('.mcp.json'))->toBe(BOOST_MCP_JSON)
        ->and(($this->read)('.cursor/mcp.json'))
        ->toBe(rootedMcpJson());
});

it('reports a file it cannot read apart from the configs, as unchecked', function (): void {
    seedFiles($this->project, ['storage/logs/laravel.log' => logOfSize(bytes: 1)]);
    chmod("{$this->project}/storage/logs/laravel.log", MODE_UNREADABLE);

    $outcomes = ($this->rewrite)();

    expect(data_get($outcomes, 'configs'))->toBe([])
        ->and(array_keys(data_get($outcomes, 'unread')))
        ->toBe(['storage/logs/laravel.log'])
        ->and(data_get($outcomes, 'unread')['storage/logs/laravel.log'])
        ->toStartWith("Could not read {$this->project}/storage/logs/laravel.log");
});

it('reports a directory it cannot open as unchecked, and roots the rest', function (): void {
    seedFiles($this->project, ['.mcp.json' => BOOST_MCP_JSON, 'docker/data/.mcp.json' => '{}']);
    chmod("{$this->project}/docker/data", MODE_UNREADABLE);

    $outcomes = ($this->rewrite)();
    chmod("{$this->project}/docker/data", TEMP_DIR_PERMISSIONS);

    expect(data_get($outcomes, 'configs'))->toBe(['.mcp.json' => null])
        ->and(array_keys(data_get($outcomes, 'unread')))
        ->toBe(['docker/data'])
        ->and(data_get($outcomes, 'unread')['docker/data'])
        ->toStartWith("Could not open {$this->project}/docker/data: ");
});

it('opens no file larger than a config can be', function (): void {
    seedFiles($this->project, [
        'storage/logs/laravel.log' => logOfSize(bytes: McpConfigRewriter::MAX_BYTES + 1),
        '.mcp.json' => str_pad(BOOST_MCP_JSON, McpConfigRewriter::MAX_BYTES),
    ]);
    chmod("{$this->project}/storage/logs/laravel.log", MODE_UNREADABLE);

    expect(($this->rewrite)())->toBe(['configs' => ['.mcp.json' => null], 'unread' => []]);
});

it('reports a server in a shape it cannot root', function (): void {
    seedFiles($this->project, ['.mcp.json' => mcpJson(boostMcpServer(['command' => 'x']))]);

    expect(($this->configs)())->toBe([
        '.mcp.json' => "holds a \"laravel-boost\" MCP server whose command names no artisan this"
            . ' package can replace',
    ]);
});
