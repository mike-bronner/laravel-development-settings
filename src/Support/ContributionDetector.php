<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

final class ContributionDetector
{
    public const MANIFEST_FILE = 'capture-manifest.json';

    public function modified(
        string $packageDir,
        array $directories,
        Manifest $sources,
        array $ignore = FileDiscovery::DEFAULT_IGNORE,
    ): array {
        $files = (new FileDiscovery)->discover(
                packageDir: $packageDir,
                paths: ['directories' => $directories, 'files' => []],
                ignore: $ignore,
            );

        return collect($files)
            ->reject(fn (string $absolutePath, string $relativePath): bool => $sources->isKnown(
                    $relativePath,
                    (string) md5_file($absolutePath),
                ))
            ->sortKeys()
            ->all();
    }
}
