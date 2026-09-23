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
 * The project is untrusted input: the workflow runs on its checkout, beside a
 * package checkout that holds a write token. So a path reached through a
 * symlink is never selected, and a path the workflow's line format cannot
 * carry fails the run instead of being split.
 *
 * The workflow runs against a package checkout with no Composer install, so
 * this class and its collaborators are required file by file. Keep it free of
 * dependencies beyond `FileDiscovery` and `Manifest`.
 */
final class ReverseSync
{
    /**
     * An empty manifest is refused: `Manifest::load()` returns one for a
     * missing, unparseable or `{}` file, and with it every tracked file would
     * look modified.
     */
    public function __construct(private Manifest $manifest)
    {
        if ($manifest->paths() === []) {
            throw new RuntimeException('The manifest records no paths. Refusing to treat every tracked file as modified.');
        }
    }

    /**
     * Expand every tracked entry to its files in the project and keep the
     * changed ones. Directory entries walk the project copy, so a file the
     * project added inside a tracked directory is included: it has no known
     * checksum. A tracked path missing from the project, or reached through a
     * symlink anywhere along it, is skipped.
     *
     * @param  array{directories?: array<array-key, string>, files?: array<array-key, string>}  $paths
     * @return array<string, string> projectPath => packagePath, both relative
     */
    public function changedFiles(string $projectDir, array $paths): array
    {
        $root = realpath($projectDir);

        if ($root === false) {
            throw new RuntimeException("Project directory {$projectDir} does not exist.");
        }

        $candidates = FileDiscovery::trackedPaths($paths['files'] ?? []);

        foreach (FileDiscovery::trackedPaths($paths['directories'] ?? []) as $targetDir => $sourceDir) {
            $candidates += $this->filesIn($root, $targetDir, $sourceDir);
        }

        $changed = [];

        foreach ($candidates as $projectPath => $packagePath) {
            $file = $root . '/' . $projectPath;

            if (! is_file($file) || realpath($file) !== $file) {
                continue;
            }

            if ($this->manifest->isKnown($projectPath, (string) md5_file($file))) {
                continue;
            }

            if (preg_match('/[\t\r\n]/', $projectPath . $packagePath) === 1) {
                throw new RuntimeException('A changed tracked path contains a tab or line break: ' . json_encode($projectPath));
            }

            $changed[$projectPath] = $packagePath;
        }

        return $changed;
    }

    /**
     * @return array<string, string> projectPath => packagePath
     */
    private function filesIn(string $root, string $targetDir, string $sourceDir): array
    {
        $dir = $root . '/' . $targetDir;

        if (! is_dir($dir)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        $files = [];

        foreach ($iterator as $file) {
            $descendant = substr($file->getPathname(), strlen($dir) + 1);
            $files[$targetDir . '/' . $descendant] = $sourceDir . '/' . $descendant;
        }

        return $files;
    }
}
