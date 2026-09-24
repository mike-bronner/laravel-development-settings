<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Tests\Fixtures;

/**
 * Stands in for PHP's `file://` stream wrapper while an operation runs, and
 * counts how often each path is opened for reading. Every call is passed on to
 * the native wrapper, so the operation behaves exactly as it would without it.
 *
 * Only the calls the reverse sync makes are implemented. Load every class the
 * operation needs before watching it: an autoloaded include would be counted
 * too, and runs through calls this class does not implement.
 */
final class ReadCountingStream
{
    /** @var resource|null */
    public $context;

    /** @var array<string, int> */
    private static array $reads = [];

    /** @var resource|false */
    private $handle = false;

    /**
     * @param  callable(): mixed  $operation
     * @return array<string, int> path => times opened for reading
     */
    public static function watch(callable $operation): array
    {
        self::$reads = [];
        self::install();

        try {
            $operation();
        } finally {
            stream_wrapper_restore('file');
        }

        return self::$reads;
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        if (str_starts_with($mode, 'r') && ! str_contains($mode, '+')) {
            self::$reads[$path] = (self::$reads[$path] ?? 0) + 1;
        }

        $this->handle = self::native(fn (): mixed => fopen($path, $mode));

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

    public function stream_set_option(int $option, int $arg1, ?int $arg2): bool
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
        return self::native(fn (): array|false => match (true) {
            ($flags & STREAM_URL_STAT_LINK) !== 0 => is_link($path) || file_exists($path) ? lstat($path) : false,
            default => file_exists($path) ? stat($path) : false,
        });
    }

    public function mkdir(string $path, int $mode, int $options): bool
    {
        return self::native(fn (): bool => mkdir($path, $mode, ($options & STREAM_MKDIR_RECURSIVE) !== 0));
    }

    private static function install(): void
    {
        stream_wrapper_unregister('file');
        stream_wrapper_register('file', self::class);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $call
     * @return T
     */
    private static function native(callable $call): mixed
    {
        stream_wrapper_restore('file');

        try {
            return $call();
        } finally {
            self::install();
        }
    }
}
