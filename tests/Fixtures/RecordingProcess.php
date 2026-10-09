<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Tests\Fixtures;

use MikeBronner\DevelopmentSettings\Support\Process;
use Override;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class RecordingProcess implements Process
{
    private array $commands = [];
    private ?array $stagedTree = null;

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

    public function commands(): array
    {
        return $this->commands;
    }

    public function log(): string
    {
        return implode("\n", $this->commands);
    }

    public function stagedTree(): ?array
    {
        return $this->stagedTree;
    }

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
