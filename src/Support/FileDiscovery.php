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
 */
final class FileDiscovery
{
    /**
     * @var list<string>
     */
    public const DEFAULT_IGNORE = ['.DS_Store', '.git', 'Thumbs.db'];

    /**
     * Resolve one configured group of entries to targetPath => sourcePath.
     *
     * A list entry (`'pint.json'`) means the package path and the project path
     * are the same. A keyed entry (`'resources/project/gitignore' => '.gitignore'`)
     * reads source-to-target, matching how Laravel itself spells a publish map.
     *
     * @param  array<array-key, string>  $entries
     * @return array<string, string> targetPath => sourcePath
     */
    public static function trackedPaths(array $entries): array
    {
        $tracked = [];

        foreach ($entries as $source => $target) {
            $tracked[$target] = is_string($source) ? $source : $target;
        }

        return $tracked;
    }

    /**
     * @param  array{directories?: array<array-key, string>, files?: array<array-key, string>}  $paths
     * @param  list<string>  $ignore
     * @return array<string, string> targetPath => absoluteSourcePath
     */
    public function discover(string $packageDir, array $paths, array $ignore = self::DEFAULT_IGNORE): array
    {
        $files = [];

        foreach (self::trackedPaths($paths['directories'] ?? []) as $targetDir => $sourceDir) {
            $sourcePath = $packageDir . '/' . $sourceDir;

            if (! is_dir($sourcePath)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($sourcePath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY,
            );

            foreach ($iterator as $file) {
                $descendant = substr($file->getPathname(), strlen($sourcePath) + 1);

                if ($this->isIgnored($sourceDir . '/' . $descendant, $ignore)
                    || $this->isIgnored($targetDir . '/' . $descendant, $ignore)) {
                    continue;
                }

                $files[$targetDir . '/' . $descendant] = $file->getPathname();
            }
        }

        foreach (self::trackedPaths($paths['files'] ?? []) as $targetFile => $sourceFile) {
            if ($this->isIgnored($sourceFile, $ignore) || $this->isIgnored($targetFile, $ignore)) {
                continue;
            }

            $sourcePath = $packageDir . '/' . $sourceFile;

            if (! file_exists($sourcePath)) {
                continue;
            }

            $files[$targetFile] = $sourcePath;
        }

        return $files;
    }

    /**
     * @param  list<string>  $ignore
     */
    private function isIgnored(string $relativePath, array $ignore): bool
    {
        $segments = explode('/', $relativePath);

        foreach ($ignore as $needle) {
            // Junk file by basename anywhere in the tree (e.g. ".DS_Store").
            if (basename($relativePath) === $needle) {
                return true;
            }

            // Junk directory segment anywhere in the path (e.g. ".git").
            if (in_array($needle, $segments, strict: true)) {
                return true;
            }
        }

        return false;
    }
}
