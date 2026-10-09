<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

final readonly class ProcessResult
{
    public function __construct(
        private int $exitCode,
        private string $output,
    ) {
    }

    public function exitCode(): int
    {
        return $this->exitCode;
    }

    public function output(): string
    {
        return $this->output;
    }

    public function failed(): bool
    {
        return $this->exitCode !== 0;
    }

    public function tail(int $lines = 20): array
    {
        $split = preg_split('/\R/', $this->output);

        return collect(match ($split) {
            false => [],
            default => $split,
        })
            ->map(fn (string $line): string => rtrim($line))
            ->reject(fn (string $line): bool => trim($line) === '')
            ->slice(-$lines)
            ->values()
            ->all();
    }
}
