<?php

declare(strict_types=1);

use Illuminate\Console\OutputStyle;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;
use Laravel\Boost\BoostManager;
use Laravel\Boost\Install\Agents\Agent;
use Laravel\Boost\Install\AgentsDetector;
use MikeBronner\DevelopmentSettings\Laravel\FlareMcpInstall;
use MikeBronner\DevelopmentSettings\Support\BoostHooks;
use MikeBronner\DevelopmentSettings\Support\FlareMcpServer;
use MikeBronner\DevelopmentSettings\Tests\Fixtures\ArrayConfig;
use MikeBronner\DevelopmentSettings\Tests\Fixtures\FixedAgentsDetector;
use MikeBronner\DevelopmentSettings\Tests\Fixtures\NoMcpAgent;
use MikeBronner\DevelopmentSettings\Tests\Fixtures\RootedApp;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

const JSON_AGENTS = [
    'claude_code' => ['.mcp.json', 'mcpServers'],
    'cursor' => ['.cursor/mcp.json', 'mcpServers'],
    'opencode' => ['opencode.json', 'mcp'],
    'amp' => ['.amp/settings.json', 'amp.mcpServers'],
    'copilot' => ['.vscode/mcp.json', 'servers'],
    'zed' => ['.zed/settings.json', 'context_servers'],
    'junie' => ['.junie/mcp/mcp.json', 'mcpServers'],
];

const OWN_FLARE_JSON = <<<JSON
    {"mcpServers":{"flare":{"type":"http","url":"https://example.test/mcp"}}}

    JSON;

const OWN_FLARE_JSONC = <<<JSON
    {
      // ours
      "mcpServers": {"flare": {"url": "https://example.test/mcp"}},
    }

    JSON;

const OWN_FLARE_TOML = <<<TOML
    [mcp_servers.flare]
    url = "https://example.test/mcp"

    [mcp_servers.other]
    url = "x"

    TOML;

const OWN_FLARE_INLINE_TOML = <<<TOML
    [mcp_servers]
    flare = { url = "https://example.test/mcp" }

    [mcp_servers.laravel-boost]
    command = "php"
    args = ["artisan", "boost:mcp"]

    TOML;

const OWN_FLARE_DOTTED_TOML = <<<TOML
    mcp_servers.flare.url = "https://example.test/mcp"

    [mcp_servers.laravel-boost]
    command = "php"

    TOML;

const OWN_FLARE_WHOLE_KEY_INLINE_TOML = <<<TOML
    mcp_servers = { "flare" = { url = "https://example.test/mcp" }, other = { url = "x" } }

    TOML;

const NOT_AN_OBJECT = <<<JSON
    ["not an object"]

    JSON;

const FLARE_CODEX_TOML = <<<TOML
    [mcp_servers.flare]
    command = "npx"
    args = ["-y", "mcp-remote", "https://flareapp.io/mcp"]

    TOML;

beforeEach(function (): void {
    $this->project = (string) realpath(makeTempDir('devset-flare-mcp-'));
    $this->workingDirectory = (string) getcwd();
    chdir($this->project);
    $this->app = new RootedApp($this->project);
    Container::setInstance($this->app);
    $this->app
        ->instance('config', new ArrayConfig);
    $this->app
        ->instance('files', new Filesystem);
    Facade::setFacadeApplication($this->app);
    $this->manager = new BoostManager;
    $this->app
        ->instance(BoostManager::class, $this->manager);
    $this->detect = function (array $inProject = [], array $onSystem = []): void {
        $detector = new FixedAgentsDetector($this->app, $this->manager, $inProject, $onSystem);
        $this->app
            ->instance(AgentsDetector::class, $detector);
    };
    ($this->detect)();
    $this->install = function (array $boost): array {
        seedFiles($this->project, ['boost.json' => json_encode($boost)]);
        $output = new BufferedOutput;
        $command = new FlareMcpInstall;
        $command->setLaravel($this->app);
        $command->setOutput(new OutputStyle(new ArrayInput([]), $output));
        $exitCode = $command->handle();

        return [$exitCode, $output->fetch()];
    };
    $this->read = fn (string $path): string => (string) file_get_contents(
            "{$this->project}/{$path}",
        );
    $this->agent = fn (string $name): Agent => $this->app
        ->make(AgentsDetector::class)
        ->getAgents()
        ->first(fn (Agent $agent): bool => $agent->name() === $name);
    $this->flareIn = fn (string $path, string $configKey): mixed => data_get(
            json_decode(($this->read)($path), associative: true),
            [$configKey, FlareMcpServer::KEY],
        );
});

afterEach(function (): void {
    chdir($this->workingDirectory);
    Facade::clearResolvedInstances();
    Container::setInstance(null);
    removeTempDir($this->project);
});

it('adds the flare server in the HTTP shape each agent\'s Boost class writes', function (
    string $name,
): void {
    [$path, $configKey] = JSON_AGENTS[$name];
    $shape = ($this->agent)($name)->httpMcpServerConfig(FlareMcpServer::URL);

    [$exitCode, $output] = ($this->install)(['mcp' => true, 'agents' => [$name]]);

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and(($this->flareIn)($path, $configKey))
        ->toBe(json_decode((string) json_encode($shape), associative: true))
        ->and($output)
        ->toContain('added the flare MCP server to', "{$path}.");
})->with(array_keys(JSON_AGENTS));

it('writes each agent\'s own shape, not a shared one', function (): void {
    ($this->install)(['mcp' => true, 'agents' => ['claude_code', 'cursor', 'opencode', 'amp']]);

    expect(($this->flareIn)('.mcp.json', 'mcpServers'))
        ->toBe(['type' => 'http', 'url' => 'https://flareapp.io/mcp'])
        ->and(($this->flareIn)('.cursor/mcp.json', 'mcpServers'))
        ->toBe(['command' => 'npx', 'args' => ['-y', 'mcp-remote', 'https://flareapp.io/mcp']])
        ->and(($this->flareIn)('opencode.json', 'mcp'))
        ->toBe([
            'type' => 'remote',
            'enabled' => true,
            'url' => 'https://flareapp.io/mcp',
            'oauth' => [],
        ])
        ->and(($this->flareIn)('.amp/settings.json', 'amp.mcpServers'))
        ->toBe(['url' => 'https://flareapp.io/mcp']);
});

it('adds the flare table to a TOML config, after the servers Boost wrote', function (): void {
    seedFiles($this->project, ['.codex/config.toml' => BOOST_MCP_TOML]);

    [$exitCode] = ($this->install)(['mcp' => true, 'agents' => ['codex']]);

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and(($this->read)('.codex/config.toml'))
        ->toBe(rtrim(BOOST_MCP_TOML) . "\n\n" . FLARE_CODEX_TOML);
});

it('keeps the laravel-boost server Boost wrote beside the flare server', function (): void {
    seedFiles($this->project, ['.mcp.json' => rootedMcpJson()]);

    ($this->install)(['mcp' => true, 'agents' => ['claude_code']]);

    expect(data_get(json_decode(($this->read)('.mcp.json'), associative: true), 'mcpServers'))
        ->toBe([
            'laravel-boost' => ['command' => 'php', 'args' => [
                'vendor/mike-bronner/laravel-development-settings/bin/rooted-testbench.php',
                'boost:mcp',
            ]],
            'flare' => ['type' => 'http', 'url' => 'https://flareapp.io/mcp'],
        ]);
});

it('leaves a config that already holds a flare server unchanged, byte for byte', function (
    string $agent,
    string $path,
    string $content,
): void {
    seedFiles($this->project, [$path => $content]);

    [$exitCode, $output] = ($this->install)(['mcp' => true, 'agents' => [$agent]]);

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and(($this->read)($path))
        ->toBe($content)
        ->and($output)
        ->toContain("{$path} already holds the flare MCP server.");
})->with([
    'plain JSON, which Boost would overwrite' => [
        'claude_code',
        '.mcp.json',
        OWN_FLARE_JSON,
    ],
    'JSON with comments' => [
        'cursor',
        '.cursor/mcp.json',
        OWN_FLARE_JSONC,
    ],
    'TOML, which Boost would replace' => [
        'codex',
        '.codex/config.toml',
        OWN_FLARE_TOML,
    ],
    'a TOML inline table, for Codex' => ['codex', '.codex/config.toml', OWN_FLARE_INLINE_TOML],
    'TOML inline table, for Grok' => ['grok_build', '.grok/config.toml', OWN_FLARE_INLINE_TOML],
    'TOML dotted keys, for Codex' => ['codex', '.codex/config.toml', OWN_FLARE_DOTTED_TOML],
    'TOML dotted keys, for Grok' => ['grok_build', '.grok/config.toml', OWN_FLARE_DOTTED_TOML],
    'the TOML config key inline, for Codex' => [
        'codex',
        '.codex/config.toml',
        OWN_FLARE_WHOLE_KEY_INLINE_TOML,
    ],
    'the TOML config key inline, for Grok' => [
        'grok_build',
        '.grok/config.toml',
        OWN_FLARE_WHOLE_KEY_INLINE_TOML,
    ],
]);

it('writes nothing when Boost configured no MCP servers', function (array $boost): void {
    ($this->detect)(['claude_code'], ['cursor']);

    [$exitCode, $output] = ($this->install)($boost);

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and(file_exists("{$this->project}/.mcp.json"))
        ->toBeFalse()
        ->and(file_exists("{$this->project}/.cursor/mcp.json"))
        ->toBeFalse()
        ->and($output)
        ->toContain('Laravel Boost configured no MCP servers, so no Flare server was added.');
})->with([
    'mcp off' => [['mcp' => false, 'agents' => ['claude_code']]],
    'no mcp key' => [['agents' => ['claude_code']]],
]);

it('uses the agents boost.json names, not the ones it detects', function (): void {
    ($this->detect)(['cursor'], ['zed']);

    ($this->install)(['mcp' => true, 'agents' => ['claude_code', 'unknown']]);

    expect(file_exists("{$this->project}/.mcp.json"))->toBeTrue()
        ->and(file_exists("{$this->project}/.cursor/mcp.json"))
        ->toBeFalse()
        ->and(file_exists("{$this->project}/.zed/settings.json"))
        ->toBeFalse();
});

it('falls back to the agents Boost detects when boost.json names none it knows', function (
    array $agents,
): void {
    ($this->detect)(['cursor', 'unknown'], ['claude_code', 'cursor']);

    ($this->install)(['mcp' => true, ...$agents]);

    expect(file_exists("{$this->project}/.mcp.json"))->toBeTrue()
        ->and(file_exists("{$this->project}/.cursor/mcp.json"))
        ->toBeTrue()
        ->and(file_exists("{$this->project}/.zed/settings.json"))
        ->toBeFalse();
})->with([
    'no agents' => [[]],
    'an empty list' => [['agents' => []]],
    'only agents Boost does not know' => [['agents' => ['unknown']]],
]);

it('writes for no agent when boost.json names only agents without MCP', function (): void {
    $this->manager
        ->registerAgent(NoMcpAgent::NAME, NoMcpAgent::class);
    ($this->detect)(['claude_code']);

    [$exitCode] = ($this->install)(['mcp' => true, 'agents' => [NoMcpAgent::NAME]]);

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and(file_exists("{$this->project}/.mcp.json"))
        ->toBeFalse()
        ->and(file_exists("{$this->project}/.no-mcp.json"))
        ->toBeFalse();
});

it('fails, naming the agent Boost could not write for, and adds the rest', function (): void {
    seedFiles($this->project, ['.mcp.json' => NOT_AN_OBJECT]);

    [$exitCode, $output] = ($this->install)(['mcp' => true, 'agents' => ['claude_code', 'cursor']]);

    expect($exitCode)->toBe(Command::FAILURE)
        ->and($output)
        ->toContain('Laravel Boost could not write the flare MCP server for Claude Code.')
        ->and(($this->read)('.mcp.json'))
        ->toBe(NOT_AN_OBJECT)
        ->and(($this->flareIn)('.cursor/mcp.json', 'mcpServers'))
        ->not
        ->toBeNull();
});

it('is hidden, under the name the plugin runs', function (): void {
    $command = new FlareMcpInstall;

    expect($command->getName())->toBe('dev-settings:flare-mcp')
        ->and($command->isHidden())
        ->toBeTrue()
        ->and(BoostHooks::FLARE_MCP_COMMAND)
        ->toEndWith(' ' . FlareMcpInstall::NAME)
        ->and(BoostHooks::TESTBENCH_FLARE_MCP_COMMAND)
        ->toEndWith(' ' . FlareMcpInstall::NAME);
});
