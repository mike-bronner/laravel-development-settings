<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use Illuminate\Support\Collection;
use LogicException;

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

    public function __construct(
        private Manifest $manifest,
        private array $managed = [],
        private CheckedFile $file = new CheckedFile,
        private ManagedSection $section = new ManagedSection,
    ) {
    }

    public function classify(string $projectDir, array $filesToPublish): array
    {
        $scan = array_fill_keys(self::GROUPS, []);

        foreach ($filesToPublish as $relativePath => $sourceFile) {
            $group = $this->group("{$projectDir}/{$relativePath}", $relativePath, $sourceFile);
            $scan[$group][$relativePath] = $sourceFile;
        }

        return $scan;
    }

    public function write(string $projectDir, string $relativePath, string $sourceFile): void
    {
        $target = "{$projectDir}/{$relativePath}";
        $file = $this->file;

        match ($this->isManaged($relativePath)) {
            true => $file->write($target, $this->composed($relativePath, $sourceFile, $target)),
            false => $file->copy($sourceFile, $target),
        };
    }

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

    public function safeOrphans(string $projectDir, array $discoveredFiles): array
    {
        [$safe] = $this->partitionOrphans($projectDir, $discoveredFiles);

        return $safe;
    }

    public function protectedOrphans(string $projectDir, array $discoveredFiles): array
    {
        [, $protected] = $this->partitionOrphans($projectDir, $discoveredFiles);

        return $protected;
    }

    private function partitionOrphans(string $projectDir, array $discoveredFiles): array
    {
        return collect($this->orphans($projectDir, $discoveredFiles))
            ->partition(fn (string $path): bool => $this->isLocalCopyKnown($projectDir, $path))
            ->map(fn (Collection $orphans): array => $orphans->values()->all())
            ->all();
    }

    private function group(string $target, string $path, string $source): string
    {
        return match (true) {
            ! file_exists($target) => self::NEW,
            $this->isManaged($path) => $this->classifyManaged($path, $target, $source),
            default => $this->compare($path, (string) md5_file($target), $source),
        };
    }

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

    private function classifyUnmarked(string $path, string $contents): string
    {
        $manifest = $this->manifest;

        return match ($manifest->isKnown($path, md5($contents))) {
            true => self::UPDATABLE,
            false => self::UNMARKED,
        };
    }

    private function compare(string $path, string $checksum, string $source): string
    {
        $manifest = $this->manifest;

        return match (true) {
            $checksum === md5_file($source) => self::UNCHANGED,
            $manifest->isKnown($path, $checksum) => self::UPDATABLE,
            default => self::MODIFIED,
        };
    }

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
