<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use LogicException;

/**
 * Classifies tracked files against the manifest and identifies orphans.
 *
 * Classification mirrors the original sync logic: a downstream file is new
 * (missing), unchanged (matches source), updatable (differs from source but is
 * a known shipped version), or modified (differs and is unknown — a local
 * edit, which must be protected).
 *
 * A managed target (see `ManagedSection`) is classified on the part above its
 * marker only, so project lines below the marker never make it "modified". It
 * adds two outcomes: "unmarked" (no marker and not a known version, so a local
 * edit that cannot be split) and "refused" (the marker more than once, so no
 * split is safe). Neither is ever written without the project's consent, and
 * a refused file is never written at all.
 *
 * Orphans are manifest paths no longer shipped that still exist downstream.
 * They are split into "safe" (an unmodified known version — deletable) and
 * "protected" (locally customized — must not be silently deleted), mirroring
 * the protect-local-edits philosophy used for updates.
 */
final class FileSync
{
    /**
     * @param  list<string>  $managed  target paths synced as a managed section
     */
    public function __construct(private Manifest $manifest, private array $managed = []) {}

    /**
     * @param  array<string, string>  $filesToPublish  relativePath => absoluteSourcePath
     * @return array{
     *     new: array<string, string>,
     *     unchanged: array<string, string>,
     *     modified: array<string, string>,
     *     updatable: array<string, string>,
     *     unmarked: array<string, string>,
     *     refused: array<string, string>,
     * }
     */
    public function classify(string $projectDir, array $filesToPublish): array
    {
        $scan = ['new' => [], 'unchanged' => [], 'modified' => [], 'updatable' => [], 'unmarked' => [], 'refused' => []];

        foreach ($filesToPublish as $relativePath => $sourceFile) {
            $destinationFile = $projectDir . '/' . $relativePath;

            if (! file_exists($destinationFile)) {
                $scan['new'][$relativePath] = $sourceFile;

                continue;
            }

            if ($this->isManaged($relativePath)) {
                $scan[$this->classifyManaged($relativePath, $destinationFile, $sourceFile)][$relativePath] = $sourceFile;

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
     * Write the source to the project. A managed target keeps the project's
     * part: the lines below its marker, or, for an unmarked file that is not a
     * known version, the whole file, which moves below the new marker intact.
     * An unmarked known version holds no project lines and is replaced.
     *
     * Throws a RuntimeException when the file cannot be read or written, so
     * no caller reports a write that did not happen.
     */
    public function write(string $projectDir, string $relativePath, string $sourceFile): void
    {
        $destinationFile = $projectDir . '/' . $relativePath;

        if (! $this->isManaged($relativePath)) {
            CheckedFile::copy($sourceFile, $destinationFile);

            return;
        }

        CheckedFile::write($destinationFile, ManagedSection::compose(
            managed: CheckedFile::read($sourceFile),
            project: file_exists($destinationFile) ? $this->projectPart($relativePath, $destinationFile) : '',
        ));
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

    private function isManaged(string $relativePath): bool
    {
        return in_array($relativePath, $this->managed, strict: true);
    }

    /**
     * @return 'unchanged'|'updatable'|'modified'|'unmarked'|'refused'
     */
    private function classifyManaged(string $relativePath, string $destinationFile, string $sourceFile): string
    {
        $contents = (string) file_get_contents($destinationFile);
        $markers = ManagedSection::markers($contents);

        if ($markers > 1) {
            return 'refused';
        }

        if ($markers === 0) {
            return $this->manifest->isKnown($relativePath, md5($contents)) ? 'updatable' : 'unmarked';
        }

        $checksum = md5((string) ManagedSection::split($contents)['managed']);

        return match (true) {
            $checksum === md5_file($sourceFile) => 'unchanged',
            $this->manifest->isKnown($relativePath, $checksum) => 'updatable',
            default => 'modified',
        };
    }

    private function projectPart(string $relativePath, string $destinationFile): string
    {
        $contents = CheckedFile::read($destinationFile);

        if (ManagedSection::markers($contents) > 1) {
            throw new LogicException("{$relativePath} holds the sync marker more than once and must not be written.");
        }

        $section = ManagedSection::split($contents);

        if ($section !== null) {
            return $section['project'];
        }

        return $this->manifest->isKnown($relativePath, md5($contents)) ? '' : $contents;
    }

    private function isLocalCopyKnown(string $projectDir, string $path): bool
    {
        return $this->manifest->isKnown($path, (string) md5_file($projectDir . '/' . $path));
    }
}
