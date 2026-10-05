<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

/**
 * The three manifests this package ships, and the sources each records:
 *
 * - `manifest.json`: the tracked `paths`, keyed on project paths.
 * - `capture-manifest.json`: the `capture` directories, keyed on package paths.
 * - `package-manifest.json`: the `package` files, keyed on project paths.
 *
 * Each is regenerated append-only from the current sources, and checked
 * against the file on disk. `composer dev-settings:manifest` writes them, and
 * CI runs the check.
 */
final class ManifestFiles
{
    private const SUCCEEDED = 0;

    private const STALE = 1;

    /**
     * @param  resource  $output
     * @param  resource  $errors
     */
    public function __construct(
        private string $packageDir,
        private mixed $output,
        private mixed $errors,
    ) {
    }

    /**
     * Write every manifest, and answer the exit status.
     */
    public function regenerate(): int
    {
        foreach ($this->generated() as $file => $manifest) {
            $manifest->dump("{$this->packageDir}/{$file}");
            $count = count($manifest->paths());
            fwrite($this->output, "{$file} regenerated ({$count} paths).\n");
        }

        return self::SUCCEEDED;
    }

    /**
     * Compare every manifest with the file on disk, and answer the exit
     * status: a failure when any would change.
     */
    public function check(): int
    {
        $stale = collect($this->generated())
            ->reject(fn (Manifest $fresh, string $file): bool => $this->isCurrent($file, $fresh))
            ->keys()
            ->implode(', ');
        $files = implode(', ', array_keys($this->sources()));

        return match ($stale) {
            '' => $this->report($this->output, "{$files} are up to date.\n", self::SUCCEEDED),
            default => $this->report(
                $this->errors,
                "{$stale} out of date. Run: composer dev-settings:manifest\n",
                self::STALE,
            ),
        };
    }

    /**
     * @return array<string, Manifest> file => the manifest its sources produce
     */
    private function generated(): array
    {
        $generator = new ManifestGenerator();

        return collect($this->sources())
            ->map(fn (array $sources, string $file): Manifest => $generator->generate(
                    $this->packageDir,
                    $sources,
                    "{$this->packageDir}/{$file}",
                ))
            ->all();
    }

    /**
     * @return array<string, array{paths: array<string, mixed>}> file => its sources
     */
    private function sources(): array
    {
        $config = new PackageConfig(require "{$this->packageDir}/" . PackageConfig::FILE);
        $ignore = $config->entries(PackageConfig::IGNORE);
        $packagePaths = $config->entries(PackageConfig::PACKAGE);

        return [
            SyncPlan::MANIFEST_FILE => ['paths' => $config->paths()],
            ContributionDetector::MANIFEST_FILE => ['paths' => [
                'directories' => $config->entries(PackageConfig::CAPTURE),
                'files' => [],
                'ignore' => $ignore,
            ]],
            ProjectKind::MANIFEST_FILE => ['paths' => [
                'directories' => [],
                'files' => data_get($packagePaths, 'files') ?? [],
                'ignore' => $ignore,
            ]],
        ];
    }

    private function isCurrent(string $file, Manifest $current): bool
    {
        $path = "{$this->packageDir}/{$file}";

        return file_exists($path) && file_get_contents($path) === $current->toJson();
    }

    /**
     * @param  resource  $stream
     */
    private function report(mixed $stream, string $message, int $status): int
    {
        fwrite($stream, $message);

        return $status;
    }
}
