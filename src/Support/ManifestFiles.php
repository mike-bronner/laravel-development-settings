<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

final class ManifestFiles
{
    private const SUCCEEDED = 0;

    private const STALE = 1;

    public function __construct(
        private string $packageDir,
        private mixed $output,
        private mixed $errors,
    ) {
    }

    public function regenerate(): int
    {
        foreach ($this->generated() as $file => $manifest) {
            $manifest->dump("{$this->packageDir}/{$file}");
            $count = count($manifest->paths());
            fwrite($this->output, "{$file} regenerated ({$count} paths).\n");
        }

        return self::SUCCEEDED;
    }

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

    private function generated(): array
    {
        $generator = new ManifestGenerator;

        return collect($this->sources())
            ->map(fn (array $sources, string $file): Manifest => $generator->generate(
                    $this->packageDir,
                    $sources,
                    "{$this->packageDir}/{$file}",
                ))
            ->all();
    }

    private function sources(): array
    {
        $config = new PackageConfig(require "{$this->packageDir}/" . PackageConfig::FILE);
        $ignore = $config->entries(PackageConfig::IGNORE);

        return [
            SyncPlan::MANIFEST_FILE => ['paths' => $config->paths()],
            ContributionDetector::MANIFEST_FILE => ['paths' => [
                'directories' => $config->entries(PackageConfig::CAPTURE),
                'files' => [],
                'ignore' => $ignore,
            ]],
            ProjectKind::MANIFEST_FILE => ['paths' => [
                'directories' => [],
                'files' => [],
                'ignore' => $ignore,
            ]],
        ];
    }

    private function isCurrent(string $file, Manifest $current): bool
    {
        $path = "{$this->packageDir}/{$file}";

        return file_exists($path) && file_get_contents($path) === $current->toJson();
    }

    private function report(mixed $stream, string $message, int $status): int
    {
        fwrite($stream, $message);

        return $status;
    }
}
