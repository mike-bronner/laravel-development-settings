<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Tests\Fixtures;

/**
 * Stands in for PHP's `file://` stream wrapper while `ReadCounter` watches an
 * operation, and tells the counter each time a path is opened for reading.
 * Every call is passed on to the native wrapper, so the operation behaves
 * exactly as it would without it.
 *
 * PHP names these methods and passes their arguments, so their names are the
 * stream wrapper protocol's own. Only the calls the reverse sync makes are
 * implemented.
 */
final class ReadCountingStream
{
    /**
     * @var resource|null
     */
    public $context;

    /**
     * @var resource|false
     */
    private $handle = false;

    // PHP calls a stream wrapper's methods by these fixed names, so they cannot be camel case.
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

    /**
     * PHP 8.6 fails `file_put_contents()`, `copy()` and `fclose()` when the
     * flush does, and a wrapper with no `stream_flush()` reports a failed one.
     */
    public function stream_flush(): bool
    {
        return fflush($this->handle);
    }

    /**
     * @return array<int|string, int>|false
     */
    public function stream_stat(): array|false
    {
        return fstat($this->handle);
    }

    public function stream_close(): void
    {
        fclose($this->handle);
    }

    /**
     * No option is supported. PHP warns about a wrapper without this method
     * when it sets the buffer of a stream.
     */
    public function stream_set_option(): bool
    {
        return false;
    }

    /**
     * A missing path answers false before `stat()` runs, because `stat()`
     * warns on one, and the warning would reach the test run.
     *
     * @return array<int|string, int>|false
     */
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

    /**
     * The counter watching this operation, from the stream context. An include
     * opens its file with no context, so it is never counted. Nothing here may
     * autoload a class: the load would open its file through this wrapper.
     */
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

    /**
     * Run the call with the native wrapper in place, then put this one back.
     *
     * @template T
     *
     * @param  callable(): T  $call
     * @return T
     */
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
