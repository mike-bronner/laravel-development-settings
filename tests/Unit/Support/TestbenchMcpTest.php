<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\TestbenchMcp;

function boostJson(array $config): string
{
    return json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}

function putFile(string $project, string $path, string $content): void
{
    if (! is_dir(dirname($project . '/' . $path))) {
        mkdir(dirname($project . '/' . $path), 0755, true);
    }

    file_put_contents($project . '/' . $path, $content);
}

function readJson(string $project, string $path): array
{
    return json_decode((string) file_get_contents($project . '/' . $path), associative: true);
}

$artisanEntry = ['command' => 'php', 'args' => ['artisan', 'boost:mcp']];
$testbenchEntry = ['command' => 'php', 'args' => ['vendor/bin/testbench', 'boost:mcp']];

it('points the entry at Testbench in every agent config Boost writes as JSON', function (string $path, string $key) use ($artisanEntry, $testbenchEntry): void {
    $project = makeTempDir();
    putFile($project, $path, boostJson([$key => ['laravel-boost' => $artisanEntry]]));

    $result = (new TestbenchMcp)->rewrite($project);

    expect($result)->toBe(['rewritten' => [$path], 'skipped' => []])
        ->and(file_get_contents($project . '/' . $path))->toBe(boostJson([$key => ['laravel-boost' => $testbenchEntry]]));

    removeTempDir($project);
})->with([
    'Claude Code' => ['.mcp.json', 'mcpServers'],
    'Antigravity' => ['.agents/mcp_config.json', 'mcpServers'],
    'Amp' => ['.amp/settings.json', 'amp.mcpServers'],
    'Cursor' => ['.cursor/mcp.json', 'mcpServers'],
    'Junie' => ['.junie/mcp/mcp.json', 'mcpServers'],
    'Kiro' => ['.kiro/settings/mcp.json', 'mcpServers'],
    'Copilot' => ['.vscode/mcp.json', 'servers'],
    'Zed' => ['.zed/settings.json', 'context_servers'],
]);

it('keeps the rest of the entry and the file, including the PHP binary Boost chose', function (): void {
    $project = makeTempDir();
    putFile($project, '.factory/mcp.json', boostJson([
        'mcpServers' => [
            'other' => ['command' => 'node', 'args' => ['server.js'], 'env' => new stdClass],
            'laravel-boost' => ['type' => 'stdio', 'command' => '/opt/php85', 'args' => ['artisan', 'boost:mcp']],
        ],
        'theme' => 'dark',
    ]));

    (new TestbenchMcp)->rewrite($project);

    expect(file_get_contents($project . '/.factory/mcp.json'))->toBe(boostJson([
        'mcpServers' => [
            'other' => ['command' => 'node', 'args' => ['server.js'], 'env' => new stdClass],
            'laravel-boost' => ['type' => 'stdio', 'command' => '/opt/php85', 'args' => ['vendor/bin/testbench', 'boost:mcp']],
        ],
        'theme' => 'dark',
    ]));

    removeTempDir($project);
});

it('rewrites the command array opencode keeps', function (string $path): void {
    $project = makeTempDir();
    putFile($project, $path, boostJson([
        '$schema' => 'https://opencode.ai/config.json',
        'mcp' => ['laravel-boost' => ['type' => 'local', 'enabled' => true, 'command' => ['php', 'artisan', 'boost:mcp']]],
    ]));

    (new TestbenchMcp)->rewrite($project);

    expect(readJson($project, $path)['mcp']['laravel-boost']['command'])->toBe(['php', 'vendor/bin/testbench', 'boost:mcp'])
        ->and(readJson($project, $path)['$schema'])->toBe('https://opencode.ai/config.json');

    removeTempDir($project);
})->with(['opencode.json', 'opencode.jsonc']);

it('writes an absolute Testbench path where Boost wrote an absolute artisan path', function (): void {
    $project = makeTempDir();
    putFile($project, '.junie/mcp/mcp.json', boostJson(['mcpServers' => ['laravel-boost' => [
        'command' => '/opt/herd/php85',
        'args' => [$project . '/vendor/orchestra/testbench-core/laravel/artisan', 'boost:mcp'],
    ]]]));

    (new TestbenchMcp)->rewrite($project);

    expect(readJson($project, '.junie/mcp/mcp.json')['mcpServers']['laravel-boost']['args'])
        ->toBe([$project . '/vendor/bin/testbench', 'boost:mcp']);

    removeTempDir($project);
});

it('rewrites only the args line of the entry table in TOML', function (string $path): void {
    $project = makeTempDir();
    $before = "model = \"o3\"\n\n[mcp_servers.other]\ncommand = \"node\"\nargs = [\"artisan\", \"boost:mcp\"]\n\n"
        . "[mcp_servers.laravel-boost]\ncommand = \"php\"\nargs = [\"artisan\", \"boost:mcp\"]\ncwd = \"/work\"\n\n"
        . "[mcp_servers.laravel-boost.env]\nAPP_ENV = \"local\"\n";
    putFile($project, $path, $before);

    $result = (new TestbenchMcp)->rewrite($project);

    expect($result['rewritten'])->toBe([$path])
        ->and(file_get_contents($project . '/' . $path))->toBe(str_replace(
            "command = \"php\"\nargs = [\"artisan\", \"boost:mcp\"]",
            "command = \"php\"\nargs = [\"vendor/bin/testbench\", \"boost:mcp\"]",
            $before,
        ));

    removeTempDir($project);
})->with(['.codex/config.toml', '.grok/config.toml']);

it('reads a quoted TOML table name and escaped strings', function (): void {
    $project = makeTempDir();
    putFile($project, '.codex/config.toml', "[mcp_servers.\"laravel-boost\"]\ncommand = \"php\"\nargs = [\"C:\\\\app\\\\artisan\", \"boost:mcp\"]\n");

    (new TestbenchMcp)->rewrite($project);

    expect(file_get_contents($project . '/.codex/config.toml'))
        ->toBe("[mcp_servers.\"laravel-boost\"]\ncommand = \"php\"\nargs = [\"" . $project . "/vendor/bin/testbench\", \"boost:mcp\"]\n");

    removeTempDir($project);
});

it('leaves an entry that already runs through Testbench alone, however the file is formatted', function (): void {
    $project = makeTempDir();
    $json = '{"mcpServers": {"laravel-boost": {"command": "php", "args": ["vendor/bin/testbench", "boost:mcp"]}}}';
    $toml = "[mcp_servers.laravel-boost]\ncommand = \"php\"\nargs = [ \"vendor/bin/testbench\" , \"boost:mcp\" ]\n";
    putFile($project, '.mcp.json', $json);
    putFile($project, '.codex/config.toml', $toml);

    expect((new TestbenchMcp)->rewrite($project))->toBe(['rewritten' => [], 'skipped' => []])
        ->and(file_get_contents($project . '/.mcp.json'))->toBe($json)
        ->and(file_get_contents($project . '/.codex/config.toml'))->toBe($toml);

    removeTempDir($project);
});

it('never adds an entry to a config that has none', function (): void {
    $project = makeTempDir();
    $content = boostJson(['mcpServers' => ['other' => ['command' => 'node']]]);
    putFile($project, '.mcp.json', $content);
    putFile($project, '.codex/config.toml', "model = \"o3\"\n");
    putFile($project, '.zed/settings.json', "{\n  // no MCP servers here\n  \"theme\": \"One\",\n}\n");

    expect((new TestbenchMcp)->rewrite($project))->toBe(['rewritten' => [], 'skipped' => []])
        ->and(file_get_contents($project . '/.mcp.json'))->toBe($content)
        ->and(file_exists($project . '/.cursor/mcp.json'))->toBeFalse();

    removeTempDir($project);
});

it('reports and keeps a JSON file with comments, which it cannot write back unchanged', function (): void {
    $project = makeTempDir();
    $content = "{\n  // Boost\n  \"context_servers\": {\"laravel-boost\": {\"command\": \"php\", \"args\": [\"artisan\", \"boost:mcp\"]}},\n}\n";
    putFile($project, '.zed/settings.json', $content);

    $result = (new TestbenchMcp)->rewrite($project);

    expect($result['skipped'])->toHaveKey('.zed/settings.json')
        ->and(file_get_contents($project . '/.zed/settings.json'))->toBe($content);

    removeTempDir($project);
});

it('reports and keeps an entry that does not run boost:mcp through artisan', function (array $entry, string $reason): void {
    $project = makeTempDir();
    $content = boostJson(['mcpServers' => ['laravel-boost' => $entry]]);
    putFile($project, '.mcp.json', $content);

    expect((new TestbenchMcp)->rewrite($project))->toBe(['rewritten' => [], 'skipped' => ['.mcp.json' => $reason]])
        ->and(file_get_contents($project . '/.mcp.json'))->toBe($content);

    removeTempDir($project);
})->with([
    'sail' => [['command' => 'vendor/bin/sail', 'args' => ['php', 'boost:mcp']], 'its Boost entry does not run boost:mcp through artisan'],
    'no boost:mcp' => [['command' => 'php', 'args' => ['artisan', 'serve']], 'its Boost entry does not run boost:mcp through artisan'],
    'boost:mcp first' => [['command' => 'php', 'args' => ['boost:mcp']], 'its Boost entry does not run boost:mcp through artisan'],
    'no args' => [['type' => 'http', 'url' => 'http://localhost'], 'its Boost entry has no args list'],
]);

it('reports and keeps a TOML entry whose args it cannot read', function (): void {
    $project = makeTempDir();
    $content = "[mcp_servers.laravel-boost]\ncommand = \"php\"\nargs = ['artisan', 'boost:mcp']\n";
    putFile($project, '.codex/config.toml', $content);

    expect((new TestbenchMcp)->rewrite($project)['skipped'])->toBe([
        '.codex/config.toml' => 'its Boost entry has no args line this package can read',
    ])
        ->and(file_get_contents($project . '/.codex/config.toml'))->toBe($content);

    removeTempDir($project);
});

it('does not read past the entry table into the next one', function (): void {
    $project = makeTempDir();
    $content = "[mcp_servers.laravel-boost]\ncommand = \"php\"\n\n[mcp_servers.other]\nargs = [\"artisan\", \"boost:mcp\"]\n";
    putFile($project, '.codex/config.toml', $content);

    expect((new TestbenchMcp)->rewrite($project)['skipped'])->toHaveKey('.codex/config.toml')
        ->and(file_get_contents($project . '/.codex/config.toml'))->toBe($content);

    removeTempDir($project);
});

it('never writes through a symlink that leaves the project', function () use ($artisanEntry): void {
    $project = makeTempDir();
    $outside = makeTempDir();
    $content = boostJson(['mcpServers' => ['laravel-boost' => $artisanEntry]]);
    file_put_contents($outside . '/mcp.json', $content);
    symlink($outside . '/mcp.json', $project . '/.mcp.json');

    expect((new TestbenchMcp)->rewrite($project)['skipped'])->toBe(['.mcp.json' => 'it resolves outside the project'])
        ->and(file_get_contents($outside . '/mcp.json'))->toBe($content);

    removeTempDir($project);
    removeTempDir($outside);
});

it('reports a config it cannot write, and carries on with the rest', function () use ($artisanEntry): void {
    $project = makeTempDir();
    putFile($project, '.cursor/mcp.json', boostJson(['mcpServers' => ['laravel-boost' => $artisanEntry]]));
    putFile($project, '.mcp.json', boostJson(['mcpServers' => ['laravel-boost' => $artisanEntry]]));
    chmod($project . '/.mcp.json', 0444);

    $result = (new TestbenchMcp)->rewrite($project);

    expect($result['rewritten'])->toBe(['.cursor/mcp.json'])
        ->and($result['skipped'])->toHaveKey('.mcp.json')
        ->and($result['skipped']['.mcp.json'])->toStartWith('Could not write');

    chmod($project . '/.mcp.json', 0644);
    removeTempDir($project);
});
