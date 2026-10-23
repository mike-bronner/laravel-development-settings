<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Laravel;

use Laravel\Boost\Mcp\ToolExecutor;
use Override;
use RuntimeException;

final class RootedToolExecutor extends ToolExecutor
{
    private const NO_ARTISAN = 'Laravel Boost no longer runs its tools through %s, so this package'
        . ' cannot run them through %s instead. Its command was: %s';

    public function __construct(private string $artisan, private string $script)
    {
    }

    public function probe(): array
    {
        return $this->buildCommand(toolClass: self::class, arguments: []);
    }

    #[Override]
    protected function buildCommand(string $toolClass, array $arguments): array
    {
        $command = parent::buildCommand(toolClass: $toolClass, arguments: $arguments);

        return match (in_array($this->artisan, $command, strict: true)) {
            true => collect($command)
                ->map(fn (string $part): string => $this->rooted($part))
                ->all(),
            false => throw new RuntimeException(
                sprintf(self::NO_ARTISAN, $this->artisan, $this->script, implode(' ', $command)),
            ),
        };
    }

    private function rooted(string $part): string
    {
        return match ($part) {
            $this->artisan => $this->script,
            default => $part,
        };
    }
}
