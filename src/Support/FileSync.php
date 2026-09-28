<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use Illuminate\Support\Collection;
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
 * the protect-local-edits philosophy used for updates. An orphan holding the
 * marker once is judged on the part above it, as classification judges a
 * managed target, and is safe only when nothing sits below the marker: those
 * lines are the project's, and deleting the file would take them with it.
 *
 * @phpstan-type Scan array{
 *     new: array<string, string>,
 *     unchanged: array<string, string>,
 *     modified: array<string, string>,
 *     updatable: array<string, string>,
 *     unmarked: array<string, string>,
 *     refused: array<string, string>,
 * }
 */
final class FileSync
{
    public const NEW = 'new';

    public const UNCHANGED = 'unchanged';

    public const MODIFIED = 'modified';

    public const UPDATABLE = 'updatable';

    public const UNMARKED = 'unmarked';

    public const REFUSED = 'refused';

    private const GROUPS = [
        self::NEW,
        self::UNCHANGED,
        self::MODIFIED,
        self::UPDATABLE,
        self::UNMARKED,
        self::REFUSED,
    ];

    /**
     * @param  list<string>  $managed  target paths synced as a managed section
     */
    public function __construct(
        private Manifest $manifest,
        private array $managed = [],
        private CheckedFile $file = new CheckedFile(),
        private ManagedSection $section = new ManagedSection(),
    ) {
    }

    /**
     * @param  array<string, string>  $filesToPublish  relativePath => absoluteSourcePath
     * @return Scan
     */
    public function classify(string $projectDir, array $filesToPublish): array
    {
        $scan = array_fill_keys(self::GROUPS, []);

        foreach ($filesToPublish as $relativePath => $sourceFile) {
            $group = $this->group("{$projectDir}/{$relativePath}", $relativePath, $sourceFile);
            $scan[$group][$relativePath] = $sourceFile;
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
        $target = "{$projectDir}/{$relativePath}";
        $file = $this->file;

        match ($this->isManaged($relativePath)) {
            true => $file->write($target, $this->composed($relativePath, $sourceFile, $target)),
            false => $file->copy($sourceFile, $target),
        };
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
        $paths = $this->manifest
            ->paths();

        return collect($paths)
            ->reject(fn (string $path): bool => in_array($path, $discoveredPaths, strict: true))
            ->filter(fn (string $path): bool => file_exists("{$projectDir}/{$path}"))
            ->filter(fn (string $path): bool => $this->isInsideProject($projectDir, $path))
            ->values()
            ->all();
    }

    /**
     * Orphans whose local copy is an unmodified known version — safe to delete.
     *
     * @param  array<string, string>  $discoveredFiles
     * @return list<string>
     */
    public function safeOrphans(string $projectDir, array $discoveredFiles): array
    {
        [$safe] = $this->partitionOrphans($projectDir, $discoveredFiles);

        return $safe;
    }

    /**
     * Orphans whose local copy was customized — must not be silently deleted.
     *
     * @param  array<string, string>  $discoveredFiles
     * @return list<string>
     */
    public function protectedOrphans(string $projectDir, array $discoveredFiles): array
    {
        [, $protected] = $this->partitionOrphans($projectDir, $discoveredFiles);

        return $protected;
    }

    /**
     * The orphans split into the safe ones and the protected ones.
     *
     * @param  array<string, string>  $discoveredFiles
     * @return array{list<string>, list<string>}
     */
    private function partitionOrphans(string $projectDir, array $discoveredFiles): array
    {
        return collect($this->orphans($projectDir, $discoveredFiles))
            ->partition(fn (string $path): bool => $this->isLocalCopyKnown($projectDir, $path))
            ->map(fn (Collection $orphans): array => $orphans->values()->all())
            ->all();
    }

    /**
     * @return 'new'|'unchanged'|'updatable'|'modified'|'unmarked'|'refused'
     */
    private function group(string $target, string $path, string $source): string
    {
        return match (true) {
            ! file_exists($target) => self::NEW,
            $this->isManaged($path) => $this->classifyManaged($path, $target, $source),
            default => $this->compare($path, (string) md5_file($target), $source),
        };
    }

    /**
     * @return 'unchanged'|'updatable'|'modified'|'unmarked'|'refused'
     */
    private function classifyManaged(string $path, string $target, string $source): string
    {
        $contents = (string) file_get_contents($target);
        $section = $this->section;
        $managedPart = (string) $section->managedPart($contents);

        return match ($section->markers($contents)) {
            0 => $this->classifyUnmarked($path, $contents),
            1 => $this->compare($path, md5($managedPart), $source),
            default => self::REFUSED,
        };
    }

    /**
     * @return 'updatable'|'unmarked'
     */
    private function classifyUnmarked(string $path, string $contents): string
    {
        $manifest = $this->manifest;

        return match ($manifest->isKnown($path, md5($contents))) {
            true => self::UPDATABLE,
            false => self::UNMARKED,
        };
    }

    /**
     * @return 'unchanged'|'updatable'|'modified'
     */
    private function compare(string $path, string $checksum, string $source): string
    {
        $manifest = $this->manifest;

        return match (true) {
            $checksum === md5_file($source) => self::UNCHANGED,
            $manifest->isKnown($path, $checksum) => self::UPDATABLE,
            default => self::MODIFIED,
        };
    }

    /**
     * Whether the path resolves to a location beneath the project directory,
     * following every symlink on the way. Unresolvable paths answer false, so
     * an orphan is only ever deleted on positive proof of containment.
     */
    private function isInsideProject(string $projectDir, string $path): bool
    {
        $resolved = realpath("{$projectDir}/{$path}");
        $root = realpath($projectDir);

        return $resolved !== false
            && $root !== false
            && str_starts_with($resolved, rtrim($root, '/') . '/');
    }

    private function isManaged(string $path): bool
    {
        return in_array($path, $this->managed, strict: true);
    }

    /**
     * The managed file as written: the source, the marker, and the project's
     * part of what the project holds now.
     */
    private function composed(string $path, string $source, string $target): string
    {
        $section = $this->section;
        $file = $this->file;

        return $section->compose(
            managed: $file->read($source),
            project: $this->existingProjectPart($path, $target),
        );
    }

    private function existingProjectPart(string $path, string $target): string
    {
        return match (file_exists($target)) {
            true => $this->projectPart($path, $target),
            false => '',
        };
    }

    private function projectPart(string $path, string $target): string
    {
        $contents = $this->file
            ->read($target);
        $section = $this->section;
        $manifest = $this->manifest;
        $refusal = "{$path} holds the sync marker more than once and must not be written.";

        return match (true) {
            $section->markers($contents) > 1 => throw new LogicException($refusal),
            $section->markers($contents) === 1 => (string) $section->projectPart($contents),
            $manifest->isKnown($path, md5($contents)) => '',
            default => $contents,
        };
    }

    /**
     * The marker decides, not `$this->managed`: a target that stops shipping
     * leaves `paths.files`, and so `paths.managed` with it, while the project
     * still holds the file the managed sync wrote.
     */
    private function isLocalCopyKnown(string $projectDir, string $path): bool
    {
        $contents = (string) file_get_contents("{$projectDir}/{$path}");
        $section = $this->section;
        $manifest = $this->manifest;
        $managedPart = (string) $section->managedPart($contents);

        return match ($section->markers($contents)) {
            1 => $section->projectPart($contents) === ''
                && $manifest->isKnown($path, md5($managedPart)),
            default => $manifest->isKnown($path, md5($contents)),
        };
    }
}
