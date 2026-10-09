<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Tests\Fixtures;

final class ReadCountingStream
{
    public mixed $context = null;
    private mixed $handle = false;

    // phpcs:disable PSR1.Methods.CamelCapsMethodName

    public function stream_open(string $path, string $mode): bool
    {
        $isRead = str_starts_with($mode, 'r') && ! str_contains($mode, '+');

        match ($isRead) {
            true => $this->counter()
                ?->count($path),
            false => null,
        };

        $this->handle = $this->native(static fn (): mixed => fopen($path, $mode));

        return $this->handle !== false;
    }

    public function stream_read(int $count): string|false
    {
        return fread($this->handle, $count);
    }

    public function stream_write(string $data): int|false
    {
        return fwrite($this->handle, $data);
    }

    public function stream_eof(): bool
    {
        return feof($this->handle);
    }

    public function stream_flush(): bool
    {
        return fflush($this->handle);
    }

    public function stream_stat(): array|false
    {
        return fstat($this->handle);
    }

    public function stream_close(): void
    {
        fclose($this->handle);
    }

    public function stream_set_option(): bool
    {
        return false;
    }

    public function url_stat(string $path, int $flags): array|false
    {
        $ofLink = ($flags & STREAM_URL_STAT_LINK) !== 0;

        return $this->native(static fn (): array|false => match (true) {
            $ofLink && (is_link($path) || file_exists($path)) => lstat($path),
            ! $ofLink && file_exists($path) => stat($path),
            default => false,
        });
    }

    // phpcs:enable PSR1.Methods.CamelCapsMethodName

    public function mkdir(string $path, int $mode, int $options): bool
    {
        $recursive = ($options & STREAM_MKDIR_RECURSIVE) !== 0;

        return $this->native(static fn (): bool => mkdir($path, $mode, $recursive));
    }

    private function counter(): ?ReadCounter
    {
        $options = match ($this->context) {
            null => [],
            default => stream_context_get_options($this->context),
        };
        ['file' => $fileOptions] = $options + ['file' => []];
        [ReadCounter::OPTION => $counter] = $fileOptions + [ReadCounter::OPTION => null];

        return $counter;
    }

    private function native(callable $call): mixed
    {
        stream_wrapper_restore('file');

        try {
            return $call();
        } finally {
            stream_wrapper_unregister('file');
            stream_wrapper_register('file', self::class);
        }
    }
}
