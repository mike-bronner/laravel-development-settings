<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Discovers the source files the package ships, from configured directories
 * (walked recursively) and explicit file paths, filtering out junk such as
 * `.DS_Store` and anything inside a `.git` directory.
 *
 * Every tracked entry names a source inside the package and a target in the
 * consuming project. The two are the same path by default, and a keyed entry
 * separates them — which is how a file ships under a name this package does not
 * have to use for its own copy. Results are keyed by the **target**, so the
 * manifest, the classifier and the orphan cleanup all keep speaking in
 * project-relative paths no matter where a source moves.
 *
 * The reverse sync workflow requires this file directly, with no Composer
 * install, so it stays free of dependencies.
 */
final class FileDiscovery
{
    /**
     * @var list<string>
     */
    public const DEFAULT_IGNORE = ['.DS_Store', '.git', 'Thumbs.db'];

    private const NO_PATHS = ['directories' => [], 'files' => []];

    /**
     * Resolve one configured group of entries to targetPath => sourcePath.
     *
     * A list entry (`'pint.json'`) means the package path and the project path
     * are the same. A keyed entry
     * (`'resources/project/gitignore' => '.gitignore'`) reads source-to-target,
     * matching how Laravel itself spells a publish map.
     *
     * @param  array<array-key, string>  $entries
     * @return array<string, string> targetPath => sourcePath
     */
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

    /**
     * @param  array{
     *     directories?: array<array-key, string>,
     *     files?: array<array-key, string>,
     * }  $paths
     * @param  list<string>  $ignore
     * @return array<string, string> targetPath => absoluteSourcePath
     */
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

    /**
     * Every file under the tracked directories the package ships.
     *
     * @param  array<array-key, string>  $directories
     * @return array<string, string> targetPath => sourcePath
     */
    private function directoryFiles(string $packageDir, array $directories): array
    {
        $files = [];

        foreach ($this->trackedPaths($directories) as $targetDir => $sourceDir) {
            $files = array_replace($files, $this->filesIn($packageDir, $targetDir, $sourceDir));
        }

        return $files;
    }

    /**
     * @return array<string, string> targetPath => sourcePath
     */
    private function filesIn(string $packageDir, string $targetDir, string $sourceDir): array
    {
        $sourcePath = "{$packageDir}/{$sourceDir}";

        return match (is_dir($sourcePath)) {
            true => $this->walk($sourcePath, $targetDir, $sourceDir),
            false => [],
        };
    }

    /**
     * @return array<string, string> targetPath => sourcePath
     */
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

    /**
     * The candidates the package ships and nobody ignores, with their sources
     * made absolute.
     *
     * @param  array<string, string>  $candidates  targetPath => sourcePath
     * @param  list<string>  $ignore
     * @return array<string, string> targetPath => absoluteSourcePath
     */
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

    /**
     * Whether any segment of the path is ignored: a junk file by its basename
     * anywhere in the tree (".DS_Store"), or a junk directory anywhere along
     * the path (".git").
     *
     * @param  list<string>  $ignore
     */
    private function isIgnored(string $relativePath, array $ignore): bool
    {
        return array_intersect($ignore, explode('/', $relativePath)) !== [];
    }
}
