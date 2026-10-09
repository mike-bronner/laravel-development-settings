<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Plugin;

use MikeBronner\DevelopmentSettings\Support\InstalledPackage;
use MikeBronner\DevelopmentSettings\Support\LegacyFingerprint;
use MikeBronner\DevelopmentSettings\Support\LegacySymlink;

final class LegacyCleanup
{
    public function __construct(
        private string $projectDir,
        private string $packageDir,
        private LegacyFingerprint $fingerprint = new LegacyFingerprint,
    ) {
    }

    public function clean(array $linkPaths): array
    {
        $projectDir = $this->projectDir;
        $symlink = new LegacySymlink([
            $this->packageDir,
            "{$projectDir}/vendor/" . InstalledPackage::LEGACY_NAME,
        ]);
        $fingerprint = $this->fingerprint;
        $links = $symlink->stale($projectDir, $linkPaths);
        $isStale = $fingerprint->isStale($projectDir);

        $symlink->remove($projectDir, $linkPaths);
        $fingerprint->remove($projectDir);

        return [
            ...collect($links)
                ->map(static fn (string $link): array => ['unlinked', $link])
                ->all(),
            ...match ($isStale) {
                true => [['stale_fingerprint', LegacyFingerprint::FILE]],
                false => [],
            },
        ];
    }
}
