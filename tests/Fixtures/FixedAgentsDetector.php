<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Tests\Fixtures;

use Illuminate\Container\Container;
use Laravel\Boost\BoostManager;
use Laravel\Boost\Install\AgentsDetector;
use Override;

final class FixedAgentsDetector extends AgentsDetector
{
    public function __construct(
        Container $container,
        BoostManager $boostManager,
        private array $inProject = [],
        private array $onSystem = [],
    ) {
        parent::__construct($container, $boostManager);
    }

    #[Override]
    public function discoverProjectInstalledAgents(string $basePath): array
    {
        return $this->inProject;
    }

    #[Override]
    public function discoverSystemInstalledAgents(): array
    {
        return $this->onSystem;
    }
}
