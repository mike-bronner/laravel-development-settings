<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

/**
 * The `hooks` entry of the config: how Laravel Boost is run, with the defaults
 * the plugin applies to a key the config leaves out.
 *
 * `command` runs captured, and `interactive_command` runs on the terminal.
 * They are two keys, not one with a flag added in code, so a plugin still
 * running from before an update keeps `--no-interaction`.
 */
final class BoostHooks
{
    public const INSTALL_COMMAND = 'php artisan boost:install';

    private const DESCRIPTION = 'Composing Laravel Boost...';

    /**
     * @param  array<string, mixed>  $hooks
     */
    public function __construct(private array $hooks)
    {
    }

    public function command(): string
    {
        return data_get($this->hooks, 'command') ?? self::INSTALL_COMMAND . ' --no-interaction';
    }

    public function interactiveCommand(): string
    {
        return data_get($this->hooks, 'interactive_command') ?? self::INSTALL_COMMAND;
    }

    public function description(): string
    {
        return data_get($this->hooks, 'description') ?? self::DESCRIPTION;
    }
}
