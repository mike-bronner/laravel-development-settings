<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

final class BoostRegistrar
{
    public const REGISTERED = 'registered';

    public const UNCHANGED = 'unchanged';

    public const UNREADABLE = 'unreadable';

    public const FILE = 'boost.json';

    public function register(
        string $projectDir,
        string $package,
        array $replaces = [],
        array $alongside = [],
    ): string {
        $path = "{$projectDir}/" . self::FILE;
        $config = $this->read($path);
        $packages = data_get($config, 'packages') ?? [];

        return match (true) {
            $config === null,
            ! is_array($packages) => self::UNREADABLE,
            default => $this->registration(
                $path,
                $config,
                $this->listed($packages, [$package, ...$alongside], $replaces),
            ),
        };
    }

    public function hasAgents(string $projectDir): bool
    {
        $agents = data_get($this->read("{$projectDir}/" . self::FILE), 'agents', []);

        return is_array($agents) && $agents !== [];
    }

    private function listed(array $packages, array $wanted, array $replaces): array
    {
        $kept = collect($packages)
            ->reject(static fn (mixed $entry): bool => in_array($entry, $replaces, strict: true))
            ->values();
        $missing = collect($wanted)
            ->reject(static fn (string $name): bool => $kept->containsStrict($name));

        return ['before' => array_values($packages), 'after' => $kept->concat($missing)->all()];
    }

    private function registration(string $path, array $config, array $packages): string
    {
        ['before' => $before, 'after' => $after] = $packages;

        return match ($after === $before) {
            true => self::UNCHANGED,
            false => $this->registerList($path, [...$config, 'packages' => $after]),
        };
    }

    private function registerList(string $path, array $config): string
    {
        ksort($config);

        $json = json_encode($config, flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($path, "{$json}\n");

        return self::REGISTERED;
    }

    private function read(string $path): ?array
    {
        return match (file_exists($path)) {
            true => $this->decode((string) file_get_contents($path)),
            false => [],
        };
    }

    private function decode(string $json): ?array
    {
        $decoded = json_decode(json: $json, associative: true);

        return match (is_array($decoded)) {
            true => $decoded,
            false => null,
        };
    }
}
