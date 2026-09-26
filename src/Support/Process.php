<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

// Runs an external command and answers its exit code, nothing else. Extracted
// so command execution can be faked in tests for the contribute flow. A caller
// that must show why a command failed uses SystemProcess::capture() instead.
interface Process
{
    public function run(string $command, ?string $workingDirectory = null, array $environment = []): int;
}
