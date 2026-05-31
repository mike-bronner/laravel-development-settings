<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Discovers the source files the package ships, from configured directories
 * (walked recursively) and explicit file paths, filtering out junk such as
 * `.DS_Store` and anything inside a `.git` directory.
 */
final class FileDiscovery
{
    /**
     * @var list<string>
     */
    public const DEFAULT_IGNORE = ['.DS_Store', '.git', 'Thumbs.db'];

    /**
     * @param  array{directories?: list<string>, files?: list<string>}  $paths
     * @param  list<string>  $ignore
     * @return array<string, string> relativePath => absoluteSourcePath
     */
    public function discover(string $packageDir, array $paths, array $ignore = self::DEFAULT_IGNORE): array
    {
        $files = [];

        foreach ($paths['directories'] ?? [] as $directory) {
            $sourceDir = $packageDir . '/' . $directory;

            if (! is_dir($sourceDir)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY,
            );

            foreach ($iterator as $file) {
                $relativePath = $directory
                    . '/'
                    . substr($file->getPathname(), strlen($sourceDir) + 1);

                if ($this->isIgnored($relativePath, $ignore)) {
                    continue;
                }

                $files[$relativePath] = $file->getPathname();
            }
        }

        foreach ($paths['files'] ?? [] as $filePath) {
            if ($this->isIgnored($filePath, $ignore)) {
                continue;
            }

            $sourceFile = $packageDir . '/' . $filePath;

            if (! file_exists($sourceFile)) {
                continue;
            }

            $files[$filePath] = $sourceFile;
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
