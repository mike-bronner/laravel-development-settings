<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Tests\Fixtures;

use MikeBronner\DevelopmentSettings\Support\Process;
use Override;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Records every command, and answers each with the exit code of the first
 * substring it contains, or the default.
 *
 * When `git add` runs, it also records every file in the working directory
 * with its contents. The clone is deleted before `Contributor::open()`
 * returns, so this snapshot is the only evidence of where each edited file
 * landed.
 */
final class RecordingProcess implements Process
{
    /**
     * @var list<string>
     */
    private array $commands = [];

    /**
     * @var array<string, string>|null relative path => contents, taken at `git add`
     */
    private ?array $stagedTree = null;

    /**
     * @param  array<string, int>  $exitCodes  command substring => exit code
     */
    public function __construct(private array $exitCodes = [], private int $default = 0)
    {
    }

    #[Override]
    public function run(string $command, ?string $workingDirectory = null): int
    {
        $this->commands[] = $command;
        $isStaging = str_starts_with($command, 'git add') && $workingDirectory !== null;

        match ($isStaging) {
            true => $this->stagedTree = $this->treeOf((string) $workingDirectory),
            false => null,
        };

        $needle = collect(array_keys($this->exitCodes))
            ->first(static fn (string $needle): bool => str_contains($command, $needle));

        return $this->exitCodes[(string) $needle] ?? $this->default;
    }

    /**
     * @return list<string>
     */
    public function commands(): array
    {
        return $this->commands;
    }

    /**
     * Every command, one per line.
     */
    public function log(): string
    {
        return implode("\n", $this->commands);
    }

    /**
     * @return array<string, string>|null
     */
    public function stagedTree(): ?array
    {
        return $this->stagedTree;
    }

    /**
     * @return array<string, string>
     */
    private function treeOf(string $directory): array
    {
        $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            );

        return collect(iterator_to_array($iterator))
            ->mapWithKeys(static fn (SplFileInfo $file): array => [
                substr($file->getPathname(), strlen($directory) + 1) => (string) file_get_contents(
                        $file->getPathname(),
                    ),
            ])
            ->sortKeys()
            ->all();
    }
}
