<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\JsonMcpConfig;
use MikeBronner\DevelopmentSettings\Support\RootedTestbench;

beforeEach(function (): void {
    $this->project = makeTempDir('devset-json-mcp-');
    $this->rewrite = fn (string $content): ?string => (new JsonMcpConfig(
            new RootedTestbench($this->project),
        ))->rewrite($content);
    $this->config = mcpJson(boostMcpServer());
});

afterEach(function (): void {
    removeTempDir($this->project);
});

it('roots every JSON shape Boost writes, and changes nothing else', function (
    string $configKey,
    array $server,
    string $commandKey,
): void {
    $rooted = [...$server, $commandKey => match ($commandKey) {
        'args' => ROOTED_MCP_ARGS,
        default => ['php', ...ROOTED_MCP_ARGS],
    }];

    expect(($this->rewrite)(mcpJson(boostMcpServer($server, $configKey))))
        ->toBe(mcpJson(boostMcpServer($rooted, $configKey)));
})->with([
    'Claude Code, Cursor, Kiro, Pi, Antigravity' => [
        'mcpServers',
        ['command' => 'php', 'args' => BOOST_MCP_ARGS],
        'args',
    ],
    'Amp' => ['amp.mcpServers', ['command' => 'php', 'args' => BOOST_MCP_ARGS], 'args'],
    'Copilot' => ['servers', ['command' => 'php', 'args' => BOOST_MCP_ARGS], 'args'],
    'Zed' => ['context_servers', ['command' => 'php', 'args' => BOOST_MCP_ARGS], 'args'],
    'Factory' => [
        'mcpServers',
        ['type' => 'stdio', 'command' => 'php', 'args' => BOOST_MCP_ARGS],
        'args',
    ],
    'OpenCode' => [
        'mcp',
        ['type' => 'local', 'enabled' => true, 'command' => ['php', ...BOOST_MCP_ARGS]],
        'command',
    ],
]);

it("roots Junie's absolute artisan with the absolute script", function (): void {
    $php = '/Users/me/Library/Application Support/Herd/bin/php85';
    $artisan = "{$this->project}/artisan";
    $written = boostMcpServer(['command' => $php, 'args' => [$artisan, 'boost:mcp']]);

    expect(($this->rewrite)(mcpJson($written)))->toBe(mcpJson(boostMcpServer([
        'command' => $php,
        'args' => ["{$this->project}/" . RootedTestbench::SCRIPT, 'boost:mcp'],
    ])));
});

it('keeps comments, trailing commas, quoting and every other server', function (): void {
    $written = <<<JSONC
        // Zed settings, with "laravel-boost" and "artisan" in a comment
        {
          "theme": "One Dark", /* "artisan" */
          context_servers: {
            'other': {"command": "php", "args": ["artisan", "serve"]},
            "laravel-boost": {
              "command": "php",
              "args": ['artisan', "boost:mcp",],
              "env": {"ARTISAN": "artisan"},
            },
          },
          "laravel-boost": {"args": ["artisan"]},
        }
        JSONC;
    $expected = str_replace("['artisan', ", "[\"" . RootedTestbench::SCRIPT . "\", ", $written);

    expect(($this->rewrite)($written))->toBe($expected)
        ->and($expected)
        ->not
        ->toBe($written);
});

it('roots the server under every config key in the file', function (): void {
    $server = ['command' => 'php', 'args' => [...BOOST_MCP_ARGS, 'artisan']];
    $rooted = ['command' => 'php', 'args' => [...ROOTED_MCP_ARGS, RootedTestbench::SCRIPT]];
    $written = [...boostMcpServer($server), ...boostMcpServer($server, 'servers')];

    expect(($this->rewrite)(mcpJson($written)))
        ->toBe(mcpJson([...boostMcpServer($rooted), ...boostMcpServer($rooted, 'servers')]));
});

it('reads escaped strings as JSON does', function (): void {
    $escaped = "a\"b\\";
    $written = boostMcpServer(['args' => ['artisan', $escaped]]);

    expect(($this->rewrite)(mcpJson($written)))
        ->toBe(mcpJson(boostMcpServer(['args' => [RootedTestbench::SCRIPT, $escaped]])));
});

it('returns an already rooted server unchanged', function (): void {
    $written = mcpJson(boostMcpServer(['command' => 'php', 'args' => ROOTED_MCP_ARGS]));

    expect(($this->rewrite)($written))->toBe($written);
});

it('reads no server from a file that is not one JSON object', function (Closure $content): void {
    expect(($this->rewrite)($content($this->config)))->toBeNull();
})->with([
    'markdown quoting a config' => [fn (string $config): string => <<<MARKDOWN
        # MCP

        {$config}
        MARKDOWN],
    'PHP' => [fn (string $config): string => '<?php return ' . var_export($config, true) . ';'],
    'an array at the root' => [fn (string $config): string => "[{$config}]"],
    'text after the object' => [fn (string $config): string => "{$config} x"],
    'a second object' => [fn (string $config): string => "{$config} {$config}"],
    'an unclosed object' => [fn (string $config): string => substr($config, 0, -1)],
    'a mismatched bracket' => [fn (string $config): string => str_replace(']', '}', $config)],
    'an unterminated string' => [
        fn (string $config): string => str_replace("artisan\"", 'artisan', $config),
    ],
    'an unterminated comment' => [fn (string $config): string => "{$config} /*"],
    'a colon in an array' => [
        fn (string $config): string => str_replace("artisan\",", "artisan\":", $config),
    ],
]);

it('reads no server from a list of servers', function (): void {
    $list = '[' . mcpJson(['laravel-boost' => ['args' => BOOST_MCP_ARGS]]) . ']';

    expect(($this->rewrite)($list))->toBeNull();
});

it('reads no server from an empty file', function (): void {
    expect(($this->rewrite)(''))->toBeNull();
});

it('reads no server from a config without the Boost server', function (array $config): void {
    expect(($this->rewrite)(mcpJson($config)))->toBeNull();
})->with([
    'another server' => [['mcpServers' => ['nightwatch' => ['args' => BOOST_MCP_ARGS]]]],
    'the server as a list' => [['mcpServers' => ['laravel-boost' => [['args' => BOOST_MCP_ARGS]]]]],
    'the server key at the root' => [['laravel-boost' => ['args' => BOOST_MCP_ARGS]]],
    'the server key one level too deep' => [['a' => boostMcpServer()]],
]);

it('fails when the server names no artisan it can replace', function (array $server): void {
    expect(fn (): ?string => ($this->rewrite)(mcpJson(boostMcpServer($server))))->toThrow(
            RuntimeException::class,
            "holds a \"laravel-boost\" MCP server whose command names no artisan this package"
                . ' can replace',
        );
})->with([
    'one command string' => [['command' => 'php artisan boost:mcp']],
    'another artisan' => [['command' => 'php', 'args' => ['/elsewhere/artisan', 'boost:mcp']]],
    'artisan outside the command' => [['command' => 'php', 'cwd' => ['artisan']]],
    'artisan keyed inside the args' => [['command' => 'php', 'args' => ['first' => 'artisan']]],
    'an empty server' => [['command' => 'php', 'args' => []]],
]);

it('reads a file that starts as an object as a possible config', function (string $prefix): void {
    expect((new JsonMcpConfig(new RootedTestbench($this->project)))->mayBeConfig($prefix))
        ->toBeTrue();
})->with([
    'an object' => ["{\"mcpServers\""],
    'an object after comments' => ["  // agent\n/* mcp */ {"],
    'an empty file' => [''],
    'a comment cut off by the prefix' => ['/* a long'],
]);

it('reads a file that starts as anything else as no config', function (string $prefix): void {
    expect((new JsonMcpConfig(new RootedTestbench($this->project)))->mayBeConfig($prefix))
        ->toBeFalse();
})->with([
    'a list' => ["[{\"mcpServers\""],
    'a log line' => ['[2026-10-15 10:00:00] local.INFO'],
    'plain text' => ['Agents'],
]);
