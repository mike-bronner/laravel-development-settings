<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

/**
 * What one sync publishes into a project: the files, the ones managed at a
 * marker, and the manifest that knows their shipped versions.
 *
 * A package repository also receives the artisan shim and the files that go
 * with it. Their checksums join the manifest only there, so an app's own
 * artisan never meets copy-sync or orphan cleanup.
 */
final class SyncPlan
{
    public const MANIFEST_FILE = 'manifest.json';

    public function __construct(
        private PackageConfig $config,
        private string $packageDir,
        private string $projectDir,
        private ProjectKind $kind = new ProjectKind(),
    ) {
    }

    /**
     * @return array<string, string> targetPath => absoluteSourcePath
     */
    public function files(): array
    {
        $files = $this->discover($this->config->paths());

        return match ($this->receivesShim()) {
            true => $files + $this->discover($this->config->entries(PackageConfig::PACKAGE)),
            false => $files,
        };
    }

    /**
     * @return list<string>
     */
    public function managed(): array
    {
        $config = $this->config;

        return match ($this->receivesShim()) {
            true => [
                ...$config->entries(PackageConfig::MANAGED),
                ...$config->entries(PackageConfig::PACKAGE_MANAGED),
            ],
            false => $config->entries(PackageConfig::MANAGED),
        };
    }

    public function manifest(): Manifest
    {
        $reader = new ManifestReader();
        $manifest = $reader->read("{$this->packageDir}/" . self::MANIFEST_FILE);

        return match ($this->receivesShim()) {
            true => new Manifest([
                ...$manifest->toArray(),
                ...$reader->read("{$this->packageDir}/" . ProjectKind::MANIFEST_FILE)->toArray(),
            ]),
            false => $manifest,
        };
    }

    private function receivesShim(): bool
    {
        return $this->kind
            ->receivesShim($this->projectDir);
    }

    /**
     * @param  array<string, mixed>  $paths
     * @return array<string, string> targetPath => absoluteSourcePath
     */
    private function discover(array $paths): array
    {
        return (new FileDiscovery())->discover(
            packageDir: $this->packageDir,
            paths: $paths,
            ignore: $this->config->entries(PackageConfig::IGNORE),
        );
    }
}
