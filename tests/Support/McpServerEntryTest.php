<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\McpServerEntry;

const FLARE_JSONC = <<<JSON
    {
      // ours, "quoted"
      "mcpServers": {"flare": {"url": "x"},},
      /* end */
    }
    JSON;

const FLARE_MARKS_IN_STRINGS = <<<JSON
    {"mcpServers": {"flare": {"url": "https://a.test//b,}", "note": "/* x */"}}}
    JSON;

const FLARE_ONLY_IN_A_COMMENT = <<<JSON
    {"mcpServers": {/* "flare": {} */ "other": {}}}
    JSON;

const FLARE_JSON5 = <<<JSON
    {mcpServers: {flare: {}}}
    JSON;

const FLARE_TOML_BODY = <<<TOML
    url = "x"

    TOML;

const FLARE_TOML_INLINE = <<<TOML
    [mcp_servers]
    flare = { url = "https://flareapp.io/mcp" }

    TOML;

const FLARE_TOML_INLINE_QUOTED = <<<TOML
    [mcp_servers]
    other = { url = "x" }
    "flare" = { url = "https://flareapp.io/mcp" }

    TOML;

const FLARE_TOML_DOTTED = <<<TOML
    mcp_servers.flare.url = "https://flareapp.io/mcp"

    TOML;

const FLARE_TOML_DOTTED_SPACED = <<<TOML
    mcp_servers . 'flare' . command = "npx"

    TOML;

const FLARE_TOML_DOTTED_IN_TABLE = <<<TOML
    [mcp_servers]
    flare.url = "https://flareapp.io/mcp"

    TOML;

const FLARE_TOML_SUB_TABLE = <<<TOML
    [mcp_servers.flare.headers]
    Accept = "x"

    TOML;

const OTHER_TOML_INLINE_IN_SERVER = <<<TOML
    [mcp_servers.laravel-boost]
    flare = { url = "x" }

    TOML;

const OTHER_TOML_TOP_LEVEL = <<<TOML
    flare = { url = "x" }
    servers.flare.url = "x"

    TOML;

const OTHER_TOML_NAME = <<<TOML
    [mcp_servers]
    flare-other = { url = "x" }
    mcp_servers.flare.url = "inside the table, so mcp_servers.mcp_servers.flare"

    TOML;

const DOTTED_KEY_HEADER = <<<TOML
    [a.b.flare]

    TOML;

const DOTTED_KEY_ASSIGNMENT = <<<TOML
    [a]
    b.flare.url = "x"

    TOML;

const QUOTED_KEY_HEADER = <<<TOML
    ["a.b".flare]

    TOML;

const FLARE_TOML_WHOLE_KEY_INLINE = <<<TOML
    mcp_servers = { flare = { url = "x" } }

    TOML;

const FLARE_TOML_WHOLE_KEY_INLINE_QUOTED = <<<TOML
    "mcp_servers" = { other = {}, 'flare' = { url = "x" } }

    TOML;

const FLARE_TOML_WHOLE_KEY_INLINE_DOTTED = <<<TOML
    mcp_servers = { "flare".url = "x" }

    TOML;

const FLARE_TOML_PARENT_INLINE = <<<TOML
    [settings]
    x = 1

    [mcp]
    servers = { flare = { url = "x" } }

    TOML;

const OTHER_TOML_WHOLE_KEY_INLINE = <<<TOML
    mcp_servers = { other = {} }

    TOML;

const OTHER_TOML_WHOLE_KEY_NESTED = <<<TOML
    mcp_servers = { other = { flare = "x" }, list = [{ flare = 1 }], note = "{ flare = 1 }" }

    TOML;

const FLARE_TOML_COMMENT = <<<TOML
    # [mcp_servers.flare]
    TOML;

beforeEach(function (): void {
    $this->project = makeTempDir('devset-mcp-entry-');
    $this->exists = function (string $path, string $content, string $key = 'mcpServers'): bool {
        seedFiles($this->project, [$path => $content]);

        return (new McpServerEntry)->exists("{$this->project}/{$path}", $key, 'flare');
    };
    $this->json = fn (array $config): string => mcpJson($config);
    $this->tomlTable = fn (string $table): string => "{$table}\n" . FLARE_TOML_BODY;
});

afterEach(function (): void {
    removeTempDir($this->project);
});

it('finds a server under the config key of a JSON file', function (string $content): void {
    expect(($this->exists)('.mcp.json', $content))->toBeTrue();
})->with([
    'pretty' => [mcpJson(['mcpServers' => ['flare' => ['url' => 'x']]])],
    'with a byte order mark' => ["\xEF\xBB\xBF" . mcpJson(['mcpServers' => ['flare' => []]])],
    'beside other servers' => [
        mcpJson(['mcpServers' => ['laravel-boost' => [], 'flare' => ['url' => 'x']]]),
    ],
    'with comments and trailing commas' => [FLARE_JSONC],
    'with comment and comma marks inside strings' => [FLARE_MARKS_IN_STRINGS],
]);

it('reads a dotted config key as one JSON member, as Boost writes it', function (): void {
    $dotted = ($this->json)(['amp.mcpServers' => ['flare' => []]]);
    $nested = ($this->json)(['amp' => ['mcpServers' => ['flare' => []]]]);

    expect(($this->exists)('.amp/settings.json', $dotted, 'amp.mcpServers'))->toBeTrue()
        ->and(($this->exists)('.amp/nested.json', $nested, 'amp.mcpServers'))
        ->toBeFalse();
});

it('finds no server in a JSON file that does not hold it under the key', function (
    string $content,
): void {
    expect(($this->exists)('.mcp.json', $content))->toBeFalse();
})->with([
    'another server only' => [mcpJson(['mcpServers' => ['laravel-boost' => []]])],
    'another config key' => [mcpJson(['servers' => ['flare' => []]])],
    'a nested flare key' => [mcpJson(['mcpServers' => ['laravel-boost' => ['flare' => []]]])],
    'servers as a list' => [mcpJson(['mcpServers' => ['flare']])],
    'not an object' => [mcpJson(['flare'])],
    'only inside a comment' => [FLARE_ONLY_IN_A_COMMENT],
    'JSON5 that PHP cannot read' => [FLARE_JSON5],
    'empty' => [''],
]);

it('finds no server when the file is missing', function (): void {
    expect((new McpServerEntry)->exists("{$this->project}/.mcp.json", 'mcpServers', 'flare'))
        ->toBeFalse();
});

it('finds a server table under the config key of a TOML file', function (string $table): void {
    $content = BOOST_MCP_TOML . "\n" . ($this->tomlTable)($table);

    expect(($this->exists)('.codex/config.toml', $content, 'mcp_servers'))->toBeTrue();
})->with([
    'bare' => ['[mcp_servers.flare]'],
    'spaced' => ['  [ mcp_servers . flare ]'],
    'double-quoted' => ["[mcp_servers.\"flare\"]"],
    'single-quoted' => ["[mcp_servers.'flare']"],
]);

it('finds a server Boost would write a second table for, in any other TOML form', function (
    string $content,
): void {
    expect(($this->exists)('.codex/config.toml', $content . "\n" . BOOST_MCP_TOML, 'mcp_servers'))
        ->toBeTrue();
})->with([
    'an inline table' => [FLARE_TOML_INLINE],
    'a quoted inline table among others' => [FLARE_TOML_INLINE_QUOTED],
    'dotted keys at the top level' => [FLARE_TOML_DOTTED],
    'spaced, quoted dotted keys' => [FLARE_TOML_DOTTED_SPACED],
    'dotted keys under the config table' => [FLARE_TOML_DOTTED_IN_TABLE],
    'only a sub-table' => [FLARE_TOML_SUB_TABLE],
    'the whole config key as an inline table' => [FLARE_TOML_WHOLE_KEY_INLINE],
    'the whole config key inline, with quoted keys' => [FLARE_TOML_WHOLE_KEY_INLINE_QUOTED],
    'the whole config key inline, with a dotted key' => [FLARE_TOML_WHOLE_KEY_INLINE_DOTTED],
]);

it('finds no server in other TOML forms that name flare elsewhere', function (
    string $content,
): void {
    expect(($this->exists)('.grok/config.toml', $content, 'mcp_servers'))->toBeFalse();
})->with([
    'a key inside another server' => [OTHER_TOML_INLINE_IN_SERVER],
    'top level or under another key' => [OTHER_TOML_TOP_LEVEL],
    'another name, or a path below the table' => [OTHER_TOML_NAME],
    'the whole config key inline, with another server' => [OTHER_TOML_WHOLE_KEY_INLINE],
    'the whole config key inline, flare elsewhere in it' => [OTHER_TOML_WHOLE_KEY_NESTED],
]);

it('reads a dotted config key as a path, as Boost writes its table header', function (): void {
    expect(($this->exists)('a.toml', DOTTED_KEY_HEADER, 'a.b'))->toBeTrue()
        ->and(($this->exists)('b.toml', DOTTED_KEY_ASSIGNMENT, 'a.b'))
        ->toBeTrue()
        ->and(($this->exists)('c.toml', QUOTED_KEY_HEADER, 'a.b'))
        ->toBeFalse()
        ->and(($this->exists)('d.toml', FLARE_TOML_PARENT_INLINE, 'mcp.servers'))
        ->toBeTrue();
});

it('finds no server table that is another server or under another key', function (
    string $table,
): void {
    expect(($this->exists)('.codex/config.toml', ($this->tomlTable)($table), 'mcp_servers'))
        ->toBeFalse();
})->with([
    'another server' => ['[mcp_servers.flare-other]'],
    'another key' => ['[servers.flare]'],
    'a sub-table of another server' => ['[mcp_servers.laravel-boost.flare]'],
    'a mismatched quote' => ["[mcp_servers.\"flare']"],
    'a comment' => [FLARE_TOML_COMMENT],
]);
