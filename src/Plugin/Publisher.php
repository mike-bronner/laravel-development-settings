<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Plugin;

use Composer\IO\IOInterface;
use MikeBronner\DevelopmentSettings\Support\FileSync;
use MikeBronner\DevelopmentSettings\Support\PackageConfig;
use MikeBronner\DevelopmentSettings\Support\SyncPlan;
use MikeBronner\DevelopmentSettings\Support\Terminal;

/**
 * Publishes the installed package into the project, after `composer install`
 * and `composer update`: removes what earlier releases left behind, syncs the
 * tracked files, registers the package with Laravel Boost, prints the summary
 * box, and runs Boost.
 */
final class Publisher
{
    /**
     * @param  list<string>  $directRequirements  the packages the project's own
     *                                            composer.json requires, dev included
     */
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

        $boost->run();
    }

    /**
     * @param  list<array{string, string}>  $lines  type => path
     * @return list<string>
     */
    private function summaryLines(array $lines): array
    {
        return collect($lines)
            ->map(fn (array $line): string => $this->summary->line(...$line))
            ->all();
    }

    /**
     * @param  list<string>  $lines
     */
    private function writeLines(array $lines): void
    {
        foreach ($lines as $line) {
            $this->inputOutput
                ->write($line);
        }
    }
}
