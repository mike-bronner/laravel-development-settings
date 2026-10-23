<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Laravel;

use Illuminate\Console\Command;
use Laravel\Boost\Mcp\Methods\CallToolWithExecutor;
use Laravel\Boost\Mcp\ToolExecutor;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;

final class McpExecutorCheck extends Command
{
    public const NAME = 'dev-settings:mcp-executor';

    private const MISSING = 'Laravel Boost has no %s class.';

    private const NOT_INJECTED = '%s no longer receives %s through its constructor, so the'
        . ' executor this package binds is not used.';

    private const NOT_BOUND = 'The container resolves Boost\'s tool executor to %s, not to %s.';

    protected $signature = self::NAME;
    protected $description = 'Check that Boost MCP tool calls run through the rooted Testbench.';
    protected $hidden = true;

    public function __construct(
        private string $executorClass = ToolExecutor::class,
        private string $callerClass = CallToolWithExecutor::class,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $this->check();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function check(): void
    {
        foreach ([$this->executorClass, $this->callerClass] as $class) {
            class_exists($class) || throw new RuntimeException(sprintf(self::MISSING, $class));
        }

        $constructor = (new ReflectionClass($this->callerClass))->getConstructor();
        $parameters = $constructor?->getParameters();
        $isInjected = collect($parameters ?? [])
            ->contains(fn (ReflectionParameter $parameter): bool => $this->isExecutor($parameter));
        $isInjected || throw new RuntimeException(
                sprintf(self::NOT_INJECTED, $this->callerClass, $this->executorClass),
            );

        $executor = $this->laravel
            ->make($this->executorClass);
        $executor instanceof RootedToolExecutor || throw new RuntimeException(
                sprintf(self::NOT_BOUND, $executor::class, RootedToolExecutor::class),
            );

        $this->line(implode(' ', $executor->probe()));
    }

    private function isExecutor(ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();

        return $type instanceof ReflectionNamedType && $type->getName() === $this->executorClass;
    }
}
