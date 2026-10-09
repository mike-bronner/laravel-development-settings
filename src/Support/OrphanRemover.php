<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use FilesystemIterator;

final class OrphanRemover
{
    public function remove(string $projectDir, string $orphanPath): void
    {
        $filePath = "{$projectDir}/{$orphanPath}";

        file_exists($filePath) && unlink($filePath);
        $this->removeEmptyDirectories(dirname($filePath), $projectDir);
    }

    private function removeEmptyDirectories(string $directory, string $stopAt): void
    {
        match (true) {
            $directory === $stopAt,
            ! is_dir($directory),
            ! $this->isEmpty($directory) => null,
            default => $this->removeAndAscend($directory, $stopAt),
        };
    }

    private function removeAndAscend(string $directory, string $stopAt): void
    {
        rmdir($directory);
        $this->removeEmptyDirectories(dirname($directory), $stopAt);
    }

    private function isEmpty(string $directory): bool
    {
        return iterator_count(new FilesystemIterator($directory)) === 0;
    }
}
