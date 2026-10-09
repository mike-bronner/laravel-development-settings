<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

final class LegacySymlink
{
    public function __construct(
        private array $packageDirs,
        private CheckedFile $file = new CheckedFile,
    ) {
    }

    public function stale(string $projectDir, array $linkPaths): array
    {
        return collect($linkPaths)
            ->filter(fn (string $linkPath): bool => $this->isStale("{$projectDir}/{$linkPath}"))
            ->values()
            ->all();
    }

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

    private function pointsInto(string $link, string $packageDir): bool
    {
        $target = (string) readlink($link);
        $resolved = $this->resolve($this->absolute($target, dirname($link)));
        $root = $this->resolve($packageDir);

        return $target !== ''
            && ($resolved === $root || str_starts_with($resolved, "{$root}/"));
    }

    private function absolute(string $target, string $linkDir): string
    {
        return match (str_starts_with($target, '/')) {
            true => $target,
            false => "{$linkDir}/{$target}",
        };
    }

    private function resolve(string $path): string
    {
        $normalized = $this->normalize($path);

        return $this->resolveFrom($normalized, [], $normalized);
    }

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

    private function append(string $real, array $tail): string
    {
        return match ($tail) {
            [] => $real,
            default => rtrim($real, '/') . '/' . implode('/', $tail),
        };
    }

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
