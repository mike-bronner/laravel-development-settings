<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class FileDiscovery
{
    public const DEFAULT_IGNORE = ['.DS_Store', '.git', 'Thumbs.db'];

    private const NO_PATHS = ['directories' => [], 'files' => []];

    public function trackedPaths(array $entries): array
    {
        $tracked = [];

        foreach ($entries as $source => $target) {
            $tracked[$target] = match (is_string($source)) {
                true => $source,
                false => $target,
            };
        }

        return $tracked;
    }

    public function discover(
        string $packageDir,
        array $paths,
        array $ignore = self::DEFAULT_IGNORE,
    ): array {
        ['directories' => $directories, 'files' => $files] = $paths + self::NO_PATHS;

        return array_replace(
                $this->shipped(
                        $packageDir,
                        $this->directoryFiles($packageDir, $directories),
                        $ignore,
                    ),
                $this->shipped($packageDir, $this->trackedPaths($files), $ignore),
            );
    }

    private function directoryFiles(string $packageDir, array $directories): array
    {
        $files = [];

        foreach ($this->trackedPaths($directories) as $targetDir => $sourceDir) {
            $files = array_replace($files, $this->filesIn($packageDir, $targetDir, $sourceDir));
        }

        return $files;
    }

    private function filesIn(string $packageDir, string $targetDir, string $sourceDir): array
    {
        $sourcePath = "{$packageDir}/{$sourceDir}";

        return match (is_dir($sourcePath)) {
            true => $this->walk($sourcePath, $targetDir, $sourceDir),
            false => [],
        };
    }

    private function walk(string $sourcePath, string $targetDir, string $sourceDir): array
    {
        $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($sourcePath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY,
            );

        $files = [];

        foreach ($iterator as $file) {
            $descendant = substr($file->getPathname(), strlen($sourcePath) + 1);
            $files["{$targetDir}/{$descendant}"] = "{$sourceDir}/{$descendant}";
        }

        return $files;
    }

    private function shipped(string $packageDir, array $candidates, array $ignore): array
    {
        $shipped = [];

        foreach ($candidates as $target => $source) {
            $shipped = array_replace($shipped, match (true) {
                $this->isIgnored($source, $ignore),
                $this->isIgnored($target, $ignore),
                ! file_exists("{$packageDir}/{$source}") => [],
                default => [$target => "{$packageDir}/{$source}"],
            });
        }

        return $shipped;
    }

    private function isIgnored(string $relativePath, array $ignore): bool
    {
        return array_intersect($ignore, explode('/', $relativePath)) !== [];
    }
}
