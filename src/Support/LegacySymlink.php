<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

/**
 * Removes the project-root symlinks earlier versions of this package created to
 * point at shared sources inside its own vendor directory (`.ai`).
 *
 * Shared sources now ship at `resources/boost/`, which Laravel Boost reads from
 * vendor directly. A leftover link is not merely redundant: it makes `.ai` a
 * window into vendor, so it keeps the consuming project from owning that
 * directory and — once the release drops the link target — leaves a dangling
 * path that breaks composition outright.
 *
 * Only a link resolving inside this package is removed. A real directory is
 * never touched: after the upgrade `.ai` belongs to the consuming project.
 */
final class LegacySymlink
{
    /**
     * @param  list<string>  $linkPaths  project-relative link paths to clean up
     * @return list<string> the link paths that were removed
     */
    public function remove(string $projectDir, string $packageDir, array $linkPaths): array
    {
        $removed = [];

        foreach ($linkPaths as $linkPath) {
            $link = $projectDir . '/' . $linkPath;

            if (! is_link($link) || ! $this->pointsInto($link, $packageDir)) {
                continue;
            }

            unlink($link);
            $removed[] = $linkPath;
        }

        return $removed;
    }

    /**
     * Whether the link resolves to the package directory or somewhere beneath
     * it.
     */
    private function pointsInto(string $link, string $packageDir): bool
    {
        $target = (string) readlink($link);

        if ($target === '') {
            return false;
        }

        $resolved = $this->resolve(str_starts_with($target, '/') ? $target : dirname($link) . '/' . $target);
        $root = $this->resolve($packageDir);

        return $resolved === $root || str_starts_with($resolved, $root . '/');
    }

    /**
     * Canonical form of a path that may not exist: `realpath()` the deepest
     * ancestor that does, then re-append the missing tail.
     *
     * Plain `realpath()` cannot do this job alone. The link that most needs
     * removing is the dangling one left by the release that moved the sources,
     * and `realpath()` answers false for it. Plain lexical normalization cannot
     * either: it would compare an unresolved `/var/…` against a resolved
     * `/private/var/…` and miss the match.
     */
    private function resolve(string $path): string
    {
        $path = $this->normalize($path);
        $head = $path;
        $tail = [];

        while (($real = realpath($head)) === false) {
            $parent = dirname($head);

            if ($parent === $head) {
                return $path;
            }

            array_unshift($tail, basename($head));
            $head = $parent;
        }

        return $tail === []
            ? $real
            : rtrim($real, '/') . '/' . implode('/', $tail);
    }

    /**
     * Collapse `.`, `..` and empty segments without touching the filesystem.
     */
    private function normalize(string $path): string
    {
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return (str_starts_with($path, '/') ? '/' : '') . implode('/', $segments);
    }
}
