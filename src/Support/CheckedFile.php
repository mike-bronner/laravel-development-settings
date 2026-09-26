<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use RuntimeException;

/**
 * Filesystem calls that throw when they fail, instead of raising a PHP warning
 * and returning false for a caller to forget. The exception carries PHP's own
 * reason, and the warning is captured rather than printed, so the log shows
 * one message.
 *
 * The reverse sync workflow requires this file directly, with no Composer
 * install, so it stays free of dependencies.
 */
final class CheckedFile
{
    public static function read(string $path): string
    {
        $contents = self::attempt(fn (): string|false => file_get_contents($path), "Could not read {$path}");

        return (string) $contents;
    }

    public static function write(string $path, string $contents): void
    {
        self::ensureDirectory(dirname($path));
        self::attempt(
            fn (): bool => file_put_contents($path, $contents) === strlen($contents),
            "Could not write {$path}",
        );
    }

    public static function copy(string $source, string $destination): void
    {
        self::ensureDirectory(dirname($destination));
        self::attempt(fn (): bool => copy($source, $destination), "Could not copy {$source} to {$destination}");
    }

    public static function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        self::attempt(
            fn (): bool => mkdir(directory: $directory, permissions: 0755, recursive: true) || is_dir($directory),
            "Could not create {$directory}",
        );
    }

    /**
     * @template T
     *
     * @param  callable(): T  $operation  answers false on failure
     * @return T
     */
    private static function attempt(callable $operation, string $failure): mixed
    {
        $reason = null;

        set_error_handler(function (int $level, string $message) use (&$reason): bool {
            $reason = $message;

            return true;
        });

        try {
            $result = $operation();
        } finally {
            restore_error_handler();
        }

        if ($result === false) {
            throw new RuntimeException($failure . ($reason === null ? '.' : ": {$reason}"));
        }

        return $result;
    }
}
