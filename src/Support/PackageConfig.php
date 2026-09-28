<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use InvalidArgumentException;

/**
 * Reads `config/development-settings.php`, with the defaults the plugin has
 * always applied to an entry the file leaves out.
 *
 * `paths` is the one entry with no default: a config that names no tracked
 * paths is refused rather than read as shipping nothing, because a sync over
 * nothing would look successful while it synced no file. Every other list is
 * named by its dotted key, and only the keys below have a default. The Boost
 * commands are read through `hooks()`.
 */
final class PackageConfig
{
    public const FILE = 'config/development-settings.php';

    public const CAPTURE = 'capture';

    public const PACKAGE = 'package';

    public const PACKAGE_MANAGED = 'package.managed';

    public const IGNORE = 'paths.ignore';

    public const MANAGED = 'paths.managed';

    public const LEGACY_SYMLINKS = 'paths.legacy_symlinks';

    private const DEFAULTS = [
        self::CAPTURE => [],
        self::PACKAGE => [],
        self::PACKAGE_MANAGED => [],
        self::IGNORE => FileDiscovery::DEFAULT_IGNORE,
        self::MANAGED => [],
        self::LEGACY_SYMLINKS => [],
    ];

    private const NO_PATHS = 'The development-settings config names no paths to sync.';

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private array $config)
    {
    }

    /**
     * The tracked paths: `directories`, `files`, `managed` and the rest.
     *
     * @return array<string, mixed>
     */
    public function paths(): array
    {
        $paths = data_get($this->config, 'paths');

        return match (is_array($paths)) {
            true => $paths,
            false => throw new InvalidArgumentException(self::NO_PATHS),
        };
    }

    /**
     * A list or map entry, or its default when the config leaves it out.
     *
     * @param  string  $key  one of the keys declared above
     * @return array<array-key, mixed>
     */
    public function entries(string $key): array
    {
        return data_get($this->config, $key) ?? self::DEFAULTS[$key];
    }

    public function hooks(): BoostHooks
    {
        return new BoostHooks(data_get($this->config, 'hooks') ?? []);
    }
}
