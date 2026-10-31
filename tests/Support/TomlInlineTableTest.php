<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\TomlInlineTable;

const NESTED_INLINE_TABLE = <<<TOML
     { flare = { url = "x", args = ["-y"] }, "quoted.key" = { a.b = 1 } } # end
    TOML;

const INLINE_TABLES_IN_AN_ARRAY = <<<TOML
     [{ flare = { url = "x" } }]
    TOML;

const INLINE_TABLE_SYNTAX_IN_STRINGS = <<<TOML
     { note = "{ flare = 1 }", 'other' = '{ x = 2 }' }
    TOML;

const PLAIN_VALUE = <<<TOML
     "https://flareapp.io/mcp"
    TOML;

it('lists every key path an inline table defines, nested and dotted ones too', function (): void {
    expect((new TomlInlineTable)->paths(['mcp_servers'], NESTED_INLINE_TABLE))->toBe([
        ['mcp_servers', 'flare'],
        ['mcp_servers', 'flare', 'url'],
        ['mcp_servers', 'flare', 'args'],
        ['mcp_servers', 'quoted.key'],
        ['mcp_servers', 'quoted.key', 'a', 'b'],
    ]);
});

it('lists no key of an inline table inside an array', function (): void {
    expect((new TomlInlineTable)->paths(['mcp_servers'], INLINE_TABLES_IN_AN_ARRAY))->toBe([]);
});

it('reads braces inside strings as text', function (): void {
    expect((new TomlInlineTable)->paths(['a'], INLINE_TABLE_SYNTAX_IN_STRINGS))
        ->toBe([['a', 'note'], ['a', 'other']]);
});

it('lists nothing for a value that is not an inline table', function (): void {
    expect((new TomlInlineTable)->paths(['url'], PLAIN_VALUE))->toBe([]);
});
