<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

/**
 * Creates and refreshes the symlinks that point a consuming project at the
 * shared sources living inside this package's vendor directory (e.g. `.ai`).
 *
 * Symlinking keeps the shared content out of the project's git/distribution
 * while staying in sync with vendor. On platforms where symlinks are
 * unavailable (typically Windows without privileges) it falls back to copying.
 */
final class SymlinkManager
{
    public const LINKED = 'linked';

    public const COPIED = 'copied';

    public const UNCHANGED = 'unchanged';

    /**
     * Ensure a single symlink (link path relative to the project) points at the
     * source path (relative to the package). Returns the action taken.
     *
     * @return self::LINKED|self::COPIED|self::UNCHANGED
     */
    public function ensure(string $projectDir, string $packageDir, string $linkPath, string $sourcePath): string
    {
        $link = $projectDir . '/' . $linkPath;
        $target = $packageDir . '/' . $sourcePath;
        $relativeTarget = $this->relativePath(from: dirname($link), to: $target);

        if (is_link($link)) {
            if (rtrim((string) readlink($link), '/') === rtrim($relativeTarget, '/')) {
                return self::UNCHANGED;
            }

            unlink($link);
        } elseif (is_dir($link)) {
            // A previously-copied directory — replace it with the symlink.
            $this->deleteTree($link);
        } elseif (file_exists($link)) {
            unlink($link);
        }

        $parent = dirname($link);

        if (! is_dir($parent)) {
            mkdir($parent, 0755, true);
        }

        if (@symlink($relativeTarget, $link)) {
            return self::LINKED;
        }

        // Symlink unsupported (e.g. Windows without privileges) — copy instead.
        $this->copyTree($target, $link);

        return self::COPIED;
    }

    private function relativePath(string $from, string $to): string
    {
        $fromParts = explode('/', rtrim($from, '/'));
        $toParts = explode('/', rtrim($to, '/'));

        while ($fromParts !== [] && $toParts !== [] && $fromParts[0] === $toParts[0]) {
            array_shift($fromParts);
            array_shift($toParts);
        }

        $up = array_fill(0, count($fromParts), '..');

        return implode('/', [...$up, ...$toParts]);
    }

    private function copyTree(string $source, string $destination): void
    {
        if (is_file($source)) {
            $this->ensureDir(dirname($destination));
            copy($source, $destination);

            return;
        }

        if (! is_dir($source)) {
            return;
        }

        $this->ensureDir($destination);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($source) + 1);
            $itemTarget = $destination . '/' . $relative;

            if ($item->isDir()) {
                $this->ensureDir($itemTarget);

                continue;
            }

            copy($item->getPathname(), $itemTarget);
        }
    }

    private function deleteTree(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            unlink($path);

            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            $item->isDir() && ! $item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($path);
    }

    private function ensureDir(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
