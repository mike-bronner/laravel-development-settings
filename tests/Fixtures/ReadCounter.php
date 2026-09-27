<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Tests\Fixtures;

/**
 * Counts how often each path is opened for reading while an operation runs.
 *
 * It puts `ReadCountingStream` in place of PHP's `file://` stream wrapper and
 * hands itself to the wrapper through the default stream context: PHP builds
 * the wrapper itself, so the context is the one way to reach it without
 * shared static state.
 *
 * Load every class the operation needs before watching it: an autoloaded
 * include would be counted too, and runs through calls the wrapper does not
 * implement.
 */
final class ReadCounter
{
    public const OPTION = 'readCounter';

    /**
     * @var array<string, int> path => times opened for reading
     */
    private array $reads = [];

    /**
     * @param  callable(): mixed  $operation
     * @return array<string, int> path => times opened for reading
     */
    public function watch(callable $operation): array
    {
        $this->reads = [];
        class_exists(ReadCountingStream::class);
        stream_context_set_default(['file' => [self::OPTION => $this]]);
        stream_wrapper_unregister('file');
        stream_wrapper_register('file', ReadCountingStream::class);

        try {
            $operation();
        } finally {
            stream_wrapper_restore('file');
            stream_context_set_default(['file' => [self::OPTION => null]]);
        }

        return $this->reads;
    }

    public function count(string $path): void
    {
        $this->reads[$path] = ($this->reads[$path] ?? 0) + 1;
    }
}
