<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

/**
 * Classifies tracked files against the manifest and identifies orphans.
 *
 * Classification mirrors the original sync logic: a downstream file is new
 * (missing), unchanged (matches source), updatable (differs from source but is
 * a known shipped version), or modified (differs and is unknown — a local
 * edit, which must be protected).
 *
 * Orphans are manifest paths no longer shipped that still exist downstream.
 * They are split into "safe" (an unmodified known version — deletable) and
 * "protected" (locally customized — must not be silently deleted), mirroring
 * the protect-local-edits philosophy used for updates.
 */
final class FileSync
{
    public function __construct(private Manifest $manifest) {}

    /**
     * @param  array<string, string>  $filesToPublish  relativePath => absoluteSourcePath
     * @return array{
     *     new: array<string, string>,
     *     unchanged: array<string, string>,
     *     modified: array<string, string>,
     *     updatable: array<string, string>,
     * }
     */
    public function classify(string $projectDir, array $filesToPublish): array
    {
        $scan = ['new' => [], 'unchanged' => [], 'modified' => [], 'updatable' => []];

        foreach ($filesToPublish as $relativePath => $sourceFile) {
            $destinationFile = $projectDir . '/' . $relativePath;

            if (! file_exists($destinationFile)) {
                $scan['new'][$relativePath] = $sourceFile;

                continue;
            }

            $localChecksum = (string) md5_file($destinationFile);

            if ($localChecksum === md5_file($sourceFile)) {
                $scan['unchanged'][$relativePath] = $sourceFile;

                continue;
            }

            if (! $this->manifest->isKnown($relativePath, $localChecksum)) {
                $scan['modified'][$relativePath] = $sourceFile;

                continue;
            }

            $scan['updatable'][$relativePath] = $sourceFile;
        }

        return $scan;
    }

    /**
     * Manifest paths no longer shipped that still exist downstream.
     *
     * A path that resolves outside the project directory is skipped. Cleanup
     * must never delete a file it does not own, and a symlinked ancestor is how
     * that happens: earlier versions of this package linked `.ai` into vendor,
     * so every `.ai/…` manifest entry pointed straight at the package's own
     * shipped sources.
     *
     * @param  array<string, string>  $discoveredFiles
     * @return list<string>
     */
    public function orphans(string $projectDir, array $discoveredFiles): array
    {
        $discoveredPaths = array_keys($discoveredFiles);
        $orphaned = [];

        foreach ($this->manifest->paths() as $manifestPath) {
            if (in_array($manifestPath, $discoveredPaths, strict: true)) {
                continue;
            }

            if (! file_exists($projectDir . '/' . $manifestPath)) {
                continue;
            }

            if (! $this->isInsideProject($projectDir, $manifestPath)) {
                continue;
            }

            $orphaned[] = $manifestPath;
        }

        return $orphaned;
    }

    /**
     * Orphans whose local copy is an unmodified known version — safe to delete.
     *
     * @param  array<string, string>  $discoveredFiles
     * @return list<string>
     */
    public function safeOrphans(string $projectDir, array $discoveredFiles): array
    {
        return array_values(array_filter(
            $this->orphans($projectDir, $discoveredFiles),
            fn (string $path): bool => $this->isLocalCopyKnown($projectDir, $path),
        ));
    }

    /**
     * Orphans whose local copy was customized — must not be silently deleted.
     *
     * @param  array<string, string>  $discoveredFiles
     * @return list<string>
     */
    public function protectedOrphans(string $projectDir, array $discoveredFiles): array
    {
        return array_values(array_filter(
            $this->orphans($projectDir, $discoveredFiles),
            fn (string $path): bool => ! $this->isLocalCopyKnown($projectDir, $path),
        ));
    }

    /**
     * Whether the path resolves to a location beneath the project directory,
     * following every symlink on the way. Unresolvable paths answer false, so
     * an orphan is only ever deleted on positive proof of containment.
     */
    private function isInsideProject(string $projectDir, string $path): bool
    {
        $resolved = realpath($projectDir . '/' . $path);
        $root = realpath($projectDir);

        if ($resolved === false || $root === false) {
            return false;
        }

        return str_starts_with($resolved, rtrim($root, '/') . '/');
    }

    private function isLocalCopyKnown(string $projectDir, string $path): bool
    {
        return $this->manifest->isKnown($path, (string) md5_file($projectDir . '/' . $path));
    }
}
