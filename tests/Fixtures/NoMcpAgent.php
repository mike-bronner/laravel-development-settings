<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Tests\Fixtures;

use Laravel\Boost\Install\Agents\Agent;
use Laravel\Boost\Install\Enums\Platform;
use Override;

final class NoMcpAgent extends Agent
{
    public const NAME = 'no_mcp';

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function displayName(): string
    {
        return 'No MCP';
    }

    #[Override]
    public function systemDetectionConfig(Platform $platform): array
    {
        return [];
    }

    #[Override]
    public function projectDetectionConfig(): array
    {
        return [];
    }

    #[Override]
    public function mcpConfigPath(): string
    {
        return '.no-mcp.json';
    }
}
