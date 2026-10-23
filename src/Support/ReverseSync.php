<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class ReverseSync
{
    private const NO_PATHS = [
        'directories' => [],
        'files' => [],
        'managed' => [],
        'managed_project_first' => [],
    ];

    private const EMPTY_MANIFEST = 'The manifest records no paths.'
        . ' Refusing to treat every tracked file as modified.';

    public function __construct(
        private Manifest $manifest,
        private CheckedFile $file = new CheckedFile,
    ) {
    }

    public function changedFiles(string $projectDir, array $paths): array
    {
        return $this->packagePaths($this->changes($projectDir, $paths));
    }

    public function export(string $projectDir, string $packageDir, array $paths): array
    {
        $changes = $this->changes($projectDir, $paths);
        $file = $this->file;

        foreach ($changes as ['package' => $packagePath, 'proposal' => $proposal]) {
            $file->write("{$packageDir}/{$packagePath}", $proposal);
        }

        return $this->packagePaths($changes);
    }

    private function changes(string $projectDir, array $paths): array
    {
        $root = $this->root($projectDir);
        $paths += self::NO_PATHS;
        $changed = [];

        foreach ($this->candidates($root, $paths) as $projectPath => $packagePath) {
            $changed += $this->change($root, $projectPath, $packagePath, $paths);
        }

        return $changed;
    }

    private function candidates(string $root, array $paths): array
    {
        ['directories' => $directories, 'files' => $files] = $paths;
        $discovery = new FileDiscovery;
        $candidates = $discovery->trackedPaths($files);

        foreach ($discovery->trackedPaths($directories) as $targetDir => $sourceDir) {
            $candidates += $this->filesIn($root, $targetDir, $sourceDir);
        }

        return $candidates;
    }

    private function root(string $projectDir): string
    {
        $recorded = $this->manifest
            ->paths();
        $root = realpath($projectDir);
        $missing = "Project directory {$projectDir} does not exist.";

        return match (true) {
            $recorded === [] => throw new RuntimeException(self::EMPTY_MANIFEST),
            $root === false => throw new RuntimeException($missing),
            default => $root,
        };
    }

    private function change(
        string $root,
        string $projectPath,
        string $packagePath,
        array $paths,
    ): array {
        $file = "{$root}/{$projectPath}";

        return match (true) {
            ! is_file($file),
            realpath($file) !== $file => [],
            default => $this->unknown(
                $projectPath,
                $packagePath,
                $this->proposal($file, $projectPath, $paths),
            ),
        };
    }

    private function unknown(string $projectPath, string $packagePath, ?string $proposal): array
    {
        $manifest = $this->manifest;

        return match (true) {
            $proposal === null,
            $manifest->isKnown($projectPath, md5($proposal)) => [],
            default => [$projectPath => ['package' => $packagePath, 'proposal' => $proposal]],
        };
    }

    private function proposal(string $file, string $projectPath, array $paths): ?string
    {
        $contents = $this->file
            ->read($file);
        ['managed' => $below, 'managed_project_first' => $above] = $paths;

        return match (true) {
            in_array($projectPath, $above, strict: true) => $this->packagePart(
                $contents,
                ManagedSection::PROJECT_ABOVE,
            ),
            in_array($projectPath, $below, strict: true) => $this->packagePart(
                $contents,
                ManagedSection::PROJECT_BELOW,
            ),
            default => $contents,
        };
    }

    private function packagePart(string $contents, string $project): ?string
    {
        return (new ManagedSection(project: $project))->managedPart($contents);
    }

    private function packagePaths(array $changes): array
    {
        return array_combine(array_keys($changes), array_column($changes, 'package'));
    }

    private function filesIn(string $root, string $targetDir, string $sourceDir): array
    {
        $dir = "{$root}/{$targetDir}";

        return match (is_dir($dir)) {
            true => $this->walk($dir, $targetDir, $sourceDir),
            false => [],
        };
    }

    private function walk(string $dir, string $targetDir, string $sourceDir): array
    {
        $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY,
            );

        $files = [];

        foreach ($iterator as $file) {
            $descendant = substr($file->getPathname(), strlen($dir) + 1);
            $files["{$targetDir}/{$descendant}"] = "{$sourceDir}/{$descendant}";
        }

        return $files;
    }
}
