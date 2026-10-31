<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Laravel;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Laravel\Boost\Contracts\SupportsMcp;
use Laravel\Boost\Install\Agents\Agent;
use Laravel\Boost\Install\AgentsDetector;
use Laravel\Boost\Support\Config;
use MikeBronner\DevelopmentSettings\Support\FlareMcpServer;
use MikeBronner\DevelopmentSettings\Support\McpServerEntry;

final class FlareMcpInstall extends Command
{
    public const NAME = 'dev-settings:flare-mcp';

    private const TEXT = [
        'no mcp' => 'Laravel Boost configured no MCP servers, so no Flare server was added.',
        'present' => '%s: %s already holds the %s MCP server.',
        'added' => '%s: added the %s MCP server to %s.',
        'failed' => 'Laravel Boost could not write the %s MCP server for %s.',
    ];

    protected $signature = self::NAME;
    protected $description = 'Add the Flare MCP server for each agent Boost configured MCP for.';
    protected $hidden = true;

    public function handle(): int
    {
        $config = $this->laravel
            ->make(Config::class);

        return match ($config->getMcp()) {
            true => $this->installFor($this->agents($config)),
            false => $this->skip(),
        };
    }

    private function skip(): int
    {
        $this->line(self::TEXT['no mcp']);

        return self::SUCCESS;
    }

    private function installFor(Collection $agents): int
    {
        $failed = $agents->reject(fn (Agent&SupportsMcp $agent): bool => $this->install($agent))
            ->map(fn (Agent $agent): string => $agent->displayName());

        return match ($failed->isEmpty()) {
            true => self::SUCCESS,
            false => $this->reportFailed($failed),
        };
    }

    private function agents(Config $config): Collection
    {
        $detector = $this->laravel
            ->make(AgentsDetector::class);
        $registered = $detector->getAgents()
            ->keyBy(fn (Agent $agent): string => $agent->name());
        $isRegistered = fn (string $name): bool => $registered->has($name);
        $detected = fn (): Collection => collect([
            ...$detector->discoverProjectInstalledAgents($this->laravel->basePath()),
            ...$detector->discoverSystemInstalledAgents(),
        ])
            ->unique()
            ->filter($isRegistered);

        return collect($config->getAgents())
            ->filter($isRegistered)
            ->whenEmpty($detected)
            ->map(fn (string $name): Agent => $registered->get($name))
            ->filter(fn (Agent $agent): bool => $agent instanceof SupportsMcp)
            ->values();
    }

    private function install(Agent&SupportsMcp $agent): bool
    {
        $name = $agent->displayName();
        $path = (string) $agent->mcpConfigPath();
        $isPresent = $path !== '' && (new McpServerEntry)->exists(
                $path,
                $agent->mcpConfigKey(),
                FlareMcpServer::KEY,
            );
        $isInstalled = $isPresent
            || $agent->installHttpMcp(FlareMcpServer::KEY, FlareMcpServer::URL);
        $message = match ($isPresent) {
            true => sprintf(self::TEXT['present'], $name, $path, FlareMcpServer::KEY),
            false => sprintf(self::TEXT['added'], $name, FlareMcpServer::KEY, $path),
        };

        match ($isInstalled) {
            true => $this->line($message),
            false => null,
        };

        return $isInstalled;
    }

    private function reportFailed(Collection $agents): int
    {
        $this->error(sprintf(self::TEXT['failed'], FlareMcpServer::KEY, $agents->implode(', ')));

        return self::FAILURE;
    }
}
