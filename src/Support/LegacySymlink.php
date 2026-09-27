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
 * Only a link resolving inside one of the package directories is removed. The
 * caller passes the current vendor directory and the one the package used
 * before its rename: Composer deletes the old one, so a link into it dangles
 * and would never match the current directory. A real directory is never
 * touched: after the upgrade `.ai` belongs to the consuming project.
 */
final class LegacySymlink
{
    /**
     * @param  list<string>  $packageDirs  directories this package lives or lived in, which
     *                                     need not exist
     */
    public function __construct(
        private array $packageDirs,
        private CheckedFile $file = new CheckedFile(),
    ) {
    }

    /**
     * The link paths that are links into one of the package directories.
     *
     * @param  list<string>  $linkPaths  project-relative link paths to clean up
     * @return list<string>
     */
    public function stale(string $projectDir, array $linkPaths): array
    {
        return collect($linkPaths)
            ->filter(fn (string $linkPath): bool => $this->isStale("{$projectDir}/{$linkPath}"))
            ->values()
            ->all();
    }

    /**
     * Remove every stale link among the link paths, and throw when one stays.
     *
     * @param  list<string>  $linkPaths  project-relative link paths to clean up
     */
    public function remove(string $projectDir, array $linkPaths): void
    {
        $file = $this->file;

        foreach ($this->stale($projectDir, $linkPaths) as $linkPath) {
            $file->unlink("{$projectDir}/{$linkPath}");
        }
    }

    private function isStale(string $link): bool
    {
        return is_link($link) && $this->pointsIntoAny($link);
    }

    private function pointsIntoAny(string $link): bool
    {
        return collect($this->packageDirs)
            ->contains(fn (string $packageDir): bool => $this->pointsInto($link, $packageDir));
    }

    /**
     * Whether the link resolves to the package directory or somewhere beneath
     * it.
     */
    private function pointsInto(string $link, string $packageDir): bool
    {
        $target = (string) readlink($link);
        $resolved = $this->resolve($this->absolute($target, dirname($link)));
        $root = $this->resolve($packageDir);

        return $target !== ''
            && ($resolved === $root || str_starts_with($resolved, "{$root}/"));
    }

    /**
     * A relative link target is relative to the directory holding the link.
     */
    private function absolute(string $target, string $linkDir): string
    {
        return match (str_starts_with($target, '/')) {
            true => $target,
            false => "{$linkDir}/{$target}",
        };
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
        $normalized = $this->normalize($path);

        return $this->resolveFrom($normalized, [], $normalized);
    }

    /**
     * Walk up from `$head` until an ancestor resolves, collecting the missing
     * tail on the way. A path with no ancestor that resolves stays as it was.
     *
     * @param  list<string>  $tail
     */
    private function resolveFrom(string $head, array $tail, string $path): string
    {
        $real = realpath($head);
        $parent = dirname($head);

        return match (true) {
            $real !== false => $this->append($real, $tail),
            $parent === $head => $path,
            default => $this->resolveFrom($parent, [basename($head), ...$tail], $path),
        };
    }

    /**
     * @param  list<string>  $tail
     */
    private function append(string $real, array $tail): string
    {
        return match ($tail) {
            [] => $real,
            default => rtrim($real, '/') . '/' . implode('/', $tail),
        };
    }

    /**
     * Collapse `.`, `..` and empty segments without touching the filesystem.
     */
    private function normalize(string $path): string
    {
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            $segments = match ($segment) {
                '', '.' => $segments,
                '..' => array_slice($segments, 0, -1),
                default => [...$segments, $segment],
            };
        }

        $root = match (str_starts_with($path, '/')) {
            true => '/',
            false => '',
        };

        return $root . implode('/', $segments);
    }
}
