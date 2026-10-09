<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

interface Process
{
    public function run(string $command, ?string $workingDirectory = null): int;
}
