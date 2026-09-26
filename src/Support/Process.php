<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

/**
 * Thin wrapper around proc_open for running external commands and returning
 * their exit code. Output is consumed but discarded. Extracted so command
 * execution can be seamed (and faked) in tests for the contribute flow.
 */
interface Process
{
    public function run(string $command, ?string $workingDirectory = null, array $environment = []): int;
}
