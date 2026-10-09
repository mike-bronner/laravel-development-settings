<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

final class SyncPlan
{
    public const MANIFEST_FILE = 'manifest.json';

    public function __construct(
        private PackageConfig $config,
        private string $packageDir,
        private string $projectDir,
        private ProjectKind $kind = new ProjectKind,
    ) {
    }

    public function files(): array
    {
        return (new FileDiscovery)->discover(
                packageDir: $this->packageDir,
                paths: $this->config->paths(),
                ignore: $this->config->entries(PackageConfig::IGNORE),
            );
    }

    public function managed(): array
    {
        return $this->config
            ->entries(PackageConfig::MANAGED);
    }

    public function manifest(): Manifest
    {
        $reader = new ManifestReader;
        $manifest = $reader->read("{$this->packageDir}/" . self::MANIFEST_FILE);
        $isTestbenchPackage = $this->kind
            ->isTestbenchPackage($this->projectDir);

        return match ($isTestbenchPackage) {
            true => new Manifest([
                ...$manifest->toArray(),
                ...$reader->read("{$this->packageDir}/" . ProjectKind::MANIFEST_FILE)->toArray(),
            ]),
            false => $manifest,
        };
    }
}
