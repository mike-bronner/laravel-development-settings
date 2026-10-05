<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Selects the tracked files a consuming project has changed, for the reverse
 * sync workflow that proposes those changes back to this package.
 *
 * A file is changed when its checksum is not a version the package ever
 * shipped for that path. An unmodified known version is left out, even an
 * older one: a project one release behind has nothing to contribute. The rule
 * is `Manifest::isKnown()`, the same one the plugin uses to protect local
 * edits, so the plugin and the workflow cannot disagree on what "modified"
 * means.
 *
 * A managed target (`paths.managed`) is judged, and exported, on the part above
 * its sync marker only. The project's lines below the marker are never
 * proposed, and neither is a managed file with no marker or with more than
 * one: neither can be split, so neither has a package part to propose.
 *
 * The project is untrusted input: the workflow runs on its checkout, beside a
 * package checkout that holds a write token. So a path reached through a
 * symlink is never selected.
 *
 * An empty manifest is refused: `ManifestReader::read()` returns one for a
 * missing, unparseable or `{}` file, and with it every tracked file would look
 * modified. Both public methods throw before they select anything.
 *
 * The workflow runs against a package checkout with no Composer install, so
 * this class and its collaborators are required file by file. Keep it free of
 * dependencies beyond `CheckedFile`, `FileDiscovery`, `Manifest`,
 * `ManifestReader` and `ManagedSection`.
 *
 * @phpstan-type Paths array{
 *     directories?: array<array-key, string>,
 *     files?: array<array-key, string>,
 *     managed?: list<string>,
 * }
 * @phpstan-type Changes array<string, array{package: string, proposal: string}>
 */
final class ReverseSync
{
    private const NO_PATHS = ['directories' => [], 'files' => [], 'managed' => []];

    private const EMPTY_MANIFEST = 'The manifest records no paths.'
        . ' Refusing to treat every tracked file as modified.';

    public function __construct(
        private Manifest $manifest,
        private CheckedFile $file = new CheckedFile(),
        private ManagedSection $section = new ManagedSection(),
    ) {
    }

    /**
     * Expand every tracked entry to its files in the project and keep the
     * changed ones. Directory entries walk the project copy, so a file the
     * project added inside a tracked directory is included: it has no known
     * checksum. A tracked path missing from the project, or reached through a
     * symlink anywhere along it, is skipped.
     *
     * @param  Paths  $paths
     * @return array<string, string> projectPath => packagePath, both relative
     */
    public function changedFiles(string $projectDir, array $paths): array
    {
        return $this->packagePaths($this->changes($projectDir, $paths));
    }

    /**
     * Write every changed file's proposal into the package checkout, and return
     * what was written. For a managed target that is the part above the marker,
     * and for every other file the whole file.
     *
     * The proposal written is the one `changes()` judged, from a single read of
     * the project file: a second read could find other contents than the ones
     * the manifest check passed.
     *
     * A directory or file that cannot be written throws, and so fails the
     * workflow job. Carrying on would drop the proposal while the job stays
     * green, because the next step only sees the files that were written.
     *
     * @param  Paths  $paths
     * @return array<string, string> projectPath => packagePath, both relative
     */
    public function export(string $projectDir, string $packageDir, array $paths): array
    {
        $changes = $this->changes($projectDir, $paths);
        $file = $this->file;

        foreach ($changes as ['package' => $packagePath, 'proposal' => $proposal]) {
            $file->write("{$packageDir}/{$packagePath}", $proposal);
        }

        return $this->packagePaths($changes);
    }

    /**
     * Every changed file, with the package path it maps to and what it
     * proposes, read once.
     *
     * @param  Paths  $paths
     * @return Changes
     */
    private function changes(string $projectDir, array $paths): array
    {
        $root = $this->root($projectDir);
        $paths += self::NO_PATHS;
        ['managed' => $managed] = $paths;
        $changed = [];

        foreach ($this->candidates($root, $paths) as $projectPath => $packagePath) {
            $changed += $this->change($root, $projectPath, $packagePath, $managed);
        }

        return $changed;
    }

    /**
     * Every tracked file the project could hold, directories expanded.
     *
     * @param  array{
     *     directories: array<array-key, string>,
     *     files: array<array-key, string>,
     * }  $paths
     * @return array<string, string> projectPath => packagePath
     */
    private function candidates(string $root, array $paths): array
    {
        ['directories' => $directories, 'files' => $files] = $paths;
        $discovery = new FileDiscovery();
        $candidates = $discovery->trackedPaths($files);

        foreach ($discovery->trackedPaths($directories) as $targetDir => $sourceDir) {
            $candidates += $this->filesIn($root, $targetDir, $sourceDir);
        }

        return $candidates;
    }

    /**
     * The resolved project directory, once the manifest is known to record
     * something to compare against.
     */
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

    /**
     * The one changed file at this path, or nothing when it is missing, is
     * reached through a symlink, proposes nothing, or is a known version.
     *
     * @param  list<string>  $managed
     * @return Changes
     */
    private function change(
        string $root,
        string $projectPath,
        string $packagePath,
        array $managed,
    ): array {
        $file = "{$root}/{$projectPath}";

        return match (true) {
            ! is_file($file),
            realpath($file) !== $file => [],
            default => $this->unknown(
                $projectPath,
                $packagePath,
                $this->proposal($file, $projectPath, $managed),
            ),
        };
    }

    /**
     * @return Changes
     */
    private function unknown(string $projectPath, string $packagePath, ?string $proposal): array
    {
        $manifest = $this->manifest;

        return match (true) {
            $proposal === null,
            $manifest->isKnown($projectPath, md5($proposal)) => [],
            default => [$projectPath => ['package' => $packagePath, 'proposal' => $proposal]],
        };
    }

    /**
     * What the file would propose upstream, or null when it proposes nothing:
     * a managed target proposes the part above its marker, any other file the
     * whole file.
     *
     * @param  list<string>  $managed
     */
    private function proposal(string $file, string $projectPath, array $managed): ?string
    {
        $contents = $this->file
            ->read($file);
        $section = $this->section;

        return match (in_array($projectPath, $managed, strict: true)) {
            true => $section->managedPart($contents),
            false => $contents,
        };
    }

    /**
     * @param  Changes  $changes
     * @return array<string, string> projectPath => packagePath
     */
    private function packagePaths(array $changes): array
    {
        return array_combine(array_keys($changes), array_column($changes, 'package'));
    }

    /**
     * @return array<string, string> projectPath => packagePath
     */
    private function filesIn(string $root, string $targetDir, string $sourceDir): array
    {
        $dir = "{$root}/{$targetDir}";

        return match (is_dir($dir)) {
            true => $this->walk($dir, $targetDir, $sourceDir),
            false => [],
        };
    }

    /**
     * @return array<string, string> projectPath => packagePath
     */
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
