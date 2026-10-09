<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

final class BoostHooks
{
    public const INSTALL_COMMAND = 'php artisan boost:install';

    public const TESTBENCH = 'php vendor/' . InstalledPackage::NAME . '/bin/rooted-testbench.php';

    public const TESTBENCH_INSTALL_COMMAND = self::TESTBENCH . ' boost:install';

    public const DISCOVER_COMMAND = self::TESTBENCH . ' package:discover';

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

    public function description(): string
    {
        return data_get($this->hooks, 'description') ?? self::DESCRIPTION;
    }
}
