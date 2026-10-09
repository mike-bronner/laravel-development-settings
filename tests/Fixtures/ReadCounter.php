<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Tests\Fixtures;

final class ReadCounter
{
    public const OPTION = 'readCounter';

    private array $reads = [];

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
