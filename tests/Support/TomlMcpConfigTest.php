<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\RootedTestbench;
use MikeBronner\DevelopmentSettings\Support\TomlMcpConfig;

beforeEach(function (): void {
    $this->project = makeTempDir('devset-toml-mcp-');
    $this->rewrite = fn (string $content): ?string => (new TomlMcpConfig(
            new RootedTestbench($this->project),
        ))->rewrite($content);
    $this->script = RootedTestbench::SCRIPT;
});

afterEach(function (): void {
    removeTempDir($this->project);
});

it('roots the server Codex and Grok Build get, and changes nothing else', function (): void {
    $written = <<<TOML
        model = "o3"
        # args = ["artisan"]

        [mcp_servers.other]
        command = "php"
        args = ["artisan", "serve"]

        [mcp_servers.laravel-boost]
        command = "php"
        args = ["artisan", "boost:mcp"]
        cwd = "/srv/app"

        [mcp_servers.laravel-boost.env]
        ARTISAN = "artisan"
        args = ["artisan"]

        TOML;
    $expected = <<<TOML
        model = "o3"
        # args = ["artisan"]

        [mcp_servers.other]
        command = "php"
        args = ["artisan", "serve"]

        [mcp_servers.laravel-boost]
        command = "php"
        args = ["{$this->script}", "boost:mcp"]
        cwd = "/srv/app"

        [mcp_servers.laravel-boost.env]
        ARTISAN = "artisan"
        args = ["artisan"]

        TOML;

    expect(($this->rewrite)($written))->toBe($expected);
});

it('roots an absolute artisan, keeping literal strings, comments and line ends', function (): void {
    $written = <<<TOML
        [mcp_servers.laravel-boost]
        args = ['{$this->project}/artisan', "boost:mcp"]  # Boost

        TOML;
    $expected = <<<TOML
        [mcp_servers.laravel-boost]
        args = ["{$this->project}/{$this->script}", "boost:mcp"]  # Boost

        TOML;
    $crlf = fn (string $toml): string => str_replace("\n", "\r\n", $toml);

    expect(($this->rewrite)($crlf($written)))->toBe($crlf($expected));
});

it('finds the server under a quoted key or any table name', function (string $table): void {
    $written = <<<TOML
        [{$table}]
        args = ["artisan", "boost:mcp"]
        TOML;
    $expected = <<<TOML
        [{$table}]
        args = ["{$this->script}", "boost:mcp"]
        TOML;

    expect(($this->rewrite)($written))->toBe($expected);
})->with([
    "mcp_servers.\"laravel-boost\"",
    'servers.laravel-boost',
    ' mcp_servers.laravel-boost ',
    "\"mcp.servers\" . laravel-boost",
]);

it('returns an already rooted server unchanged', function (): void {
    $written = <<<TOML
        [mcp_servers.laravel-boost]
        args = ["{$this->script}", "boost:mcp"]

        TOML;

    expect(($this->rewrite)($written))->toBe($written);
});

it('reads no server from a file without it', function (string $table): void {
    $written = <<<TOML
        [{$table}]
        args = ["artisan"]

        TOML;

    expect(($this->rewrite)($written))->toBeNull();
})->with([
    'another server' => ['mcp_servers.other'],
    'only its env table' => ['mcp_servers.laravel-boost.env'],
    'a longer name' => ['mcp_servers.laravel-boost-2'],
    'a top-level table' => ['laravel-boost'],
    'a table two levels down' => ['a.b.laravel-boost'],
]);

it('reads no server from an empty file', function (): void {
    expect(($this->rewrite)(''))->toBeNull();
});

it('leaves the args of the table after the server alone', function (): void {
    $written = <<<TOML
        [mcp_servers.laravel-boost]
        args = ["artisan"]
        [[tools]]
        args = ["artisan"]

        TOML;
    $expected = <<<TOML
        [mcp_servers.laravel-boost]
        args = ["{$this->script}"]
        [[tools]]
        args = ["artisan"]

        TOML;

    expect(($this->rewrite)($written))->toBe($expected);
});

it('fails when the server names no artisan it can replace', function (string $body): void {
    $written = <<<TOML
        [mcp_servers.laravel-boost]
        {$body}

        TOML;

    expect(fn (): ?string => ($this->rewrite)($written))->toThrow(
            RuntimeException::class,
            "holds a \"laravel-boost\" MCP server whose args name no artisan this package"
                . ' can replace',
        );
})->with([
    'one command string' => ["command = \"php artisan boost:mcp\""],
    'args across lines' => ["args = [\n  \"artisan\",\n]"],
    'another artisan' => ["args = [\"/elsewhere/artisan\"]"],
]);

it('reads every TOML file as a config', function (): void {
    expect((new TomlMcpConfig(new RootedTestbench($this->project)))->mayBeConfig('codex = 1'))
        ->toBeTrue();
});
