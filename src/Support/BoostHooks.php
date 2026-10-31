<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

final class BoostHooks
{
    public const INSTALL_COMMAND = 'php artisan boost:install';

    public const TESTBENCH = 'php ' . RootedTestbench::SCRIPT;

    public const TESTBENCH_INSTALL_COMMAND = self::TESTBENCH . ' boost:install';

    public const DISCOVER_COMMAND = self::TESTBENCH . ' package:discover';

    public const MCP_CHECK_COMMAND = self::TESTBENCH . ' dev-settings:mcp-executor';

    public const FLARE_MCP_COMMAND = 'php artisan dev-settings:flare-mcp';

    public const TESTBENCH_FLARE_MCP_COMMAND = self::TESTBENCH . ' dev-settings:flare-mcp';

    private const NO_INTERACTION = ' --no-interaction';

    private const DESCRIPTION = 'Composing Laravel Boost...';

    private const TESTBENCH_KEYS = 'testbench_';

    public function __construct(
        private array $hooks,
        private string $keyPrefix = '',
        private string $installCommand = self::INSTALL_COMMAND,
    ) {
    }

    public function throughTestbench(): self
    {
        return new self($this->hooks, self::TESTBENCH_KEYS, self::TESTBENCH_INSTALL_COMMAND);
    }

    public function command(): string
    {
        return data_get($this->hooks, "{$this->keyPrefix}command")
            ?? $this->installCommand . self::NO_INTERACTION;
    }

    public function interactiveCommand(): string
    {
        return data_get($this->hooks, "{$this->keyPrefix}interactive_command")
            ?? $this->installCommand;
    }

    public function installCommand(): string
    {
        return $this->installCommand;
    }

    public function discoverCommand(): string
    {
        return data_get($this->hooks, 'testbench_discover_command') ?? self::DISCOVER_COMMAND;
    }

    public function mcpCheckCommand(): string
    {
        return data_get($this->hooks, 'testbench_mcp_check_command') ?? self::MCP_CHECK_COMMAND;
    }

    public function flareMcpCommand(): string
    {
        $default = match ($this->keyPrefix) {
            self::TESTBENCH_KEYS => self::TESTBENCH_FLARE_MCP_COMMAND,
            default => self::FLARE_MCP_COMMAND,
        };

        return data_get($this->hooks, "{$this->keyPrefix}flare_mcp_command") ?? $default;
    }

    public function description(): string
    {
        return data_get($this->hooks, 'description') ?? self::DESCRIPTION;
    }
}
