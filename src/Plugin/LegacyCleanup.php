<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Plugin;

use MikeBronner\DevelopmentSettings\Support\InstalledPackage;
use MikeBronner\DevelopmentSettings\Support\LegacyFingerprint;
use MikeBronner\DevelopmentSettings\Support\LegacySymlink;

/**
 * Removes what earlier releases left in a project: the `.ai` link into vendor
 * and the Boost fingerprint file.
 *
 * It runs before anything inspects the project tree: while the legacy link
 * stands, every `.ai/…` manifest path resolves into vendor and orphan cleanup
 * would delete this package's own sources. A link into the vendor path the
 * package used before its rename is stale too, even though Composer has
 * already deleted what it points at.
 */
final class LegacyCleanup
{
    public function __construct(
        private string $projectDir,
        private string $packageDir,
        private LegacyFingerprint $fingerprint = new LegacyFingerprint(),
    ) {
    }

    /**
     * Remove the stale links among the link paths, and the fingerprint file,
     * and answer a summary line for each.
     *
     * @param  list<string>  $linkPaths
     * @return list<array{string, string}> type => path, for the summary
     */
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
