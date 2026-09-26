<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

final readonly class ProcessResult
{
    public function __construct(
        public int $exitCode,
        public string $output,
    ) {}

    public function failed(): bool
    {
        return $this->exitCode !== 0;
    }

    public function tail(int $lines = 20): array
    {
        $nonBlank = array_filter(
            array_map(rtrim(...), preg_split('/\R/', $this->output) ?: []),
            fn (string $line): bool => trim($line) !== '',
        );

        return array_values(array_slice($nonBlank, -$lines));
    }
}
