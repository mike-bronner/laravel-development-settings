<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use InvalidArgumentException;

final class PackageConfig
{
    public const FILE = 'config/development-settings.php';

    public const CAPTURE = 'capture';

    public const IGNORE = 'paths.ignore';

    public const MANAGED = 'paths.managed';

    public const LEGACY_SYMLINKS = 'paths.legacy_symlinks';

    private const DEFAULTS = [
        self::CAPTURE => [],
        self::IGNORE => FileDiscovery::DEFAULT_IGNORE,
        self::MANAGED => [],
        self::LEGACY_SYMLINKS => [],
    ];

    private const NO_PATHS = 'The development-settings config names no paths to sync.';

    public function __construct(private array $config)
    {
    }

    public function paths(): array
    {
        $paths = data_get($this->config, 'paths');

        return match (is_array($paths)) {
            true => $paths,
            false => throw new InvalidArgumentException(self::NO_PATHS),
        };
    }

    public function entries(string $key): array
    {
        return data_get($this->config, $key) ?? self::DEFAULTS[$key];
    }

    public function hooks(): BoostHooks
    {
        return new BoostHooks(data_get($this->config, 'hooks') ?? []);
    }
}
