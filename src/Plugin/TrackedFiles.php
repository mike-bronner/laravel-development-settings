<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Plugin;

use Composer\IO\IOInterface;
use MikeBronner\DevelopmentSettings\Support\FileSync;
use MikeBronner\DevelopmentSettings\Support\OrphanRemover;
use RuntimeException;

/**
 * One sync of the tracked files into a project: classify them, write what
 * needs no answer, list what happened, ask about the rest, and count it all.
 *
 * New and known-version files need no answer from the user, so they are
 * written before the summary lists them: a write that fails is listed as
 * failed, never as created or updated. As with a failed Boost run, the
 * failure is reported with its cause and the run carries on.
 *
 * @phpstan-import-type Scan from FileSync
 */
final class TrackedFiles
{
    private const ERROR = <<<TEXT
        <error>  %s</error>
        TEXT;

    private const NOT_UPDATED = '%s was not updated. %s'
        . ' Fix the cause, then run the Composer command again.';

    /**
     * @var Scan
     */
    private array $scan;

    /**
     * @var list<string>
     */
    private array $safeOrphans = [];

    /**
     * @var list<string>
     */
    private array $protectedOrphans = [];

    /**
     * @var array<string, string> path => why its write failed
     */
    private array $failedWrites = [];

    public function __construct(
        private FileSync $fileSync,
        private string $projectDir,
        private IOInterface $inputOutput,
        private OrphanRemover $orphanRemover = new OrphanRemover,
    ) {
    }

    /**
     * Classify the files against the project, and find the orphans, before
     * anything is written.
     *
     * @param  array<string, string>  $files  targetPath => absoluteSourcePath
     */
    public function classify(array $files): void
    {
        $fileSync = $this->fileSync;
        $this->scan = $fileSync->classify($this->projectDir, $files);
        $this->safeOrphans = $fileSync->safeOrphans($this->projectDir, $files);
        $this->protectedOrphans = $fileSync->protectedOrphans($this->projectDir, $files);
    }

    /**
     * Write the new and the known-version files. One that cannot be written
     * leaves its group, and is listed and counted as failed instead.
     */
    public function writeUnattended(): void
    {
        foreach ([FileSync::NEW, FileSync::UPDATABLE] as $group) {
            $this->scan[$group] = collect($this->scan[$group])
                ->filter(fn (string $source, string $path): bool => $this->written($path, $source))
                ->all();
        }
    }

    /**
     * What happened to each file, in the order the summary lists them.
     *
     * @return list<array{string, string}> type => path
     */
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

    /**
     * Ask about every file kept so far, write and delete what was agreed,
     * report every failed write, and count the outcome.
     */
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

    /**
     * @param  list<string>  $paths
     * @return list<array{string, string}>
     */
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

    /**
     * Write the agreed files among the kept ones. A kept file that was not
     * agreed to, or whose write fails, is skipped.
     *
     * @param  array<string, string>  $consented  path => absoluteSourcePath
     */
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

    /**
     * Write one tracked file, and record why it failed when it does.
     */
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

    /**
     * Delete every safe orphan, and each protected one the user chose.
     *
     * @param  list<string>  $orphansToDelete
     */
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
