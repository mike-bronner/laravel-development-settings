<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Plugin;

use Composer\IO\IOInterface;
use MikeBronner\DevelopmentSettings\Support\FileSync;
use MikeBronner\DevelopmentSettings\Support\OrphanRemover;
use RuntimeException;

final class TrackedFiles
{
    private const ERROR = <<<TEXT
        <error>  %s</error>
        TEXT;

    private const NOT_UPDATED = '%s was not updated. %s'
        . ' Fix the cause, then run the Composer command again.';

    private array $scan;
    private array $safeOrphans = [];
    private array $protectedOrphans = [];
    private array $failedWrites = [];
    private array $files = [];

    public function __construct(
        private FileSync $fileSync,
        private string $projectDir,
        private IOInterface $inputOutput,
        private OrphanRemover $orphanRemover = new OrphanRemover,
    ) {
    }

    public function classify(array $files): void
    {
        $fileSync = $this->fileSync;
        $this->files = $files;
        $this->scan = $fileSync->classify($this->projectDir, $files);
        $this->safeOrphans = $fileSync->safeOrphans($this->projectDir, $files);
        $this->protectedOrphans = $fileSync->protectedOrphans($this->projectDir, $files);
    }

    public function writeUnattended(): void
    {
        foreach ([FileSync::NEW, FileSync::UPDATABLE] as $group) {
            $this->scan[$group] = collect($this->scan[$group])
                ->filter(fn (string $source, string $path): bool => $this->written($path, $source))
                ->all();
        }
    }

    public function summaryLines(): array
    {
        return [
            ...$this->lines('failed', array_keys($this->failedWrites)),
            ...$this->lines('created', array_keys($this->scan[FileSync::NEW])),
            ...$this->lines('updated', array_keys($this->scan[FileSync::UPDATABLE])),
            ...$this->lines('modified', array_keys($this->scan[FileSync::MODIFIED])),
            ...$this->lines('unmarked', array_keys($this->scan[FileSync::UNMARKED])),
            ...$this->lines('refused', array_keys($this->scan[FileSync::REFUSED])),
            ...$this->lines('removed', $this->safeOrphans),
            ...$this->lines('orphan_protected', $this->protectedOrphans),
        ];
    }

    public function settle(Consent $consent, Tally $tally): void
    {
        [
            FileSync::MODIFIED => $modified,
            FileSync::UNMARKED => $unmarked,
            FileSync::REFUSED => $refused,
        ] = $this->scan;
        $overwrite = $consent->overwrite(array_keys($modified));
        $convert = $consent->convert(array_keys($unmarked));
        $consent->explainRefused(array_keys($refused));
        $orphansToDelete = $consent->orphansToDelete($this->protectedOrphans);

        $this->countUnattended($tally);
        $this->writeConsented($tally, [
            ...array_intersect_key($modified, array_flip($overwrite)),
            ...array_intersect_key($unmarked, array_flip($convert)),
        ]);
        $this->reportFailures();
        $this->removeOrphans($tally, $orphansToDelete);
    }

    public function warnOverrides(): void
    {
        (new IgnoreOverrideNotice($this->inputOutput, $this->projectDir))->warn($this->files);
    }

    private function lines(string $type, array $paths): array
    {
        return collect($paths)
            ->map(static fn (string $path): array => [$type, $path])
            ->all();
    }

    private function countUnattended(Tally $tally): void
    {
        $skipped = count($this->scan[FileSync::REFUSED]) + count($this->failedWrites);

        $tally->add(Tally::NEW, count($this->scan[FileSync::NEW]));
        $tally->add(Tally::UPDATED, count($this->scan[FileSync::UPDATABLE]));
        $tally->add(Tally::UNCHANGED, count($this->scan[FileSync::UNCHANGED]));
        $tally->add(Tally::SKIPPED, $skipped);
    }

    private function writeConsented(Tally $tally, array $consented): void
    {
        $kept = [...$this->scan[FileSync::MODIFIED], ...$this->scan[FileSync::UNMARKED]];

        foreach ($kept as $path => $sourceFile) {
            $isWritten = array_key_exists($path, $consented) && $this->written($path, $sourceFile);
            $tally->add(match ($isWritten) {
                true => Tally::UPDATED,
                false => Tally::SKIPPED,
            });
        }
    }

    private function written(string $path, string $sourceFile): bool
    {
        try {
            $this->fileSync
                ->write($this->projectDir, $path, $sourceFile);
        } catch (RuntimeException $exception) {
            $this->failedWrites[$path] = $exception->getMessage();

            return false;
        }

        return true;
    }

    private function reportFailures(): void
    {
        $inputOutput = $this->inputOutput;

        foreach ($this->failedWrites as $path => $failure) {
            $cause = rtrim($failure, '.') . '.';
            $message = sprintf(self::NOT_UPDATED, $path, $cause);
            $inputOutput->writeError(sprintf(self::ERROR, $message));
        }
    }

    private function removeOrphans(Tally $tally, array $orphansToDelete): void
    {
        $chosen = array_intersect($this->protectedOrphans, $orphansToDelete);
        $orphanRemover = $this->orphanRemover;

        foreach ([...$this->safeOrphans, ...$chosen] as $orphanPath) {
            $orphanRemover->remove($this->projectDir, $orphanPath);
        }

        $tally->add(Tally::REMOVED, count($this->safeOrphans) + count($chosen));
        $tally->add(Tally::SKIPPED, count($this->protectedOrphans) - count($chosen));
    }
}
