<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Plugin;

use Composer\IO\IOInterface;
use MikeBronner\DevelopmentSettings\Support\FileSync;
use MikeBronner\DevelopmentSettings\Support\PackageConfig;
use MikeBronner\DevelopmentSettings\Support\SyncPlan;
use MikeBronner\DevelopmentSettings\Support\Terminal;

final class Publisher
{
    public function __construct(
        private IOInterface $inputOutput,
        private Terminal $terminal,
        private string $projectDir,
        private string $packageDir,
        private array $directRequirements = [],
        private Summary $summary = new Summary,
    ) {
    }

    public function publish(PackageConfig $config): void
    {
        $plan = new SyncPlan($config, $this->packageDir, $this->projectDir);
        $legacyLines = (new LegacyCleanup($this->projectDir, $this->packageDir))
            ->clean($config->entries(PackageConfig::LEGACY_SYMLINKS));
        $trackedFiles = new TrackedFiles(
                new FileSync($plan->manifest(), managed: $plan->managed()),
                $this->projectDir,
                $this->inputOutput,
            );
        $trackedFiles->classify($plan->files());
        $boost = new BoostRun(
                $this->inputOutput,
                $this->terminal,
                $config->hooks(),
                $this->projectDir,
                $this->directRequirements,
            );
        $boost->register();
        $trackedFiles->writeUnattended();

        $this->writeLines([
            ...$this->summary->header(),
            ...$this->summaryLines([
                ...$trackedFiles->summaryLines(),
                ...$legacyLines,
                ...$boost->summaryLines(),
            ]),
        ]);

        $tally = new Tally;
        $trackedFiles->settle(new Consent($this->inputOutput), $tally);
        $tally->add(Tally::REMOVED, count($legacyLines));
        $boost->count($tally);
        $this->writeLines($this->summary->footer($tally));
        $trackedFiles->warnOverrides();

        $boost->run();
        (new ClaudeNotice($this->inputOutput, $this->projectDir))->settle($boost->composedFiles());
    }

    private function summaryLines(array $lines): array
    {
        return collect($lines)
            ->map(fn (array $line): string => $this->summary->line(...$line))
            ->all();
    }

    private function writeLines(array $lines): void
    {
        foreach ($lines as $line) {
            $this->inputOutput
                ->write($line);
        }
    }
}
