<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Plugin;

use Composer\IO\IOInterface;
use MikeBronner\DevelopmentSettings\Support\BoostHooks;

final class AfterBoost
{
    public function __construct(
        private IOInterface $inputOutput,
        private string $projectDir,
        private BoostHooks $hooks,
    ) {
    }

    public function settle(BoostRun $boost): void
    {
        (new RootedMcp($this->inputOutput, $this->projectDir, $this->hooks))
            ->settle($boost->startedAt());
        (new ClaudeNotice($this->inputOutput, $this->projectDir))->settle($boost->composedFiles());
    }
}
