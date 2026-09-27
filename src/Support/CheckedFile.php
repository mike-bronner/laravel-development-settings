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
    private const DIRECTORY_PERMISSIONS = 0755;

    private ?string $reason = null;

    public function read(string $path): string
    {
        return (string) $this->attempt(
            static fn (): string|false => file_get_contents($path),
            "Could not read {$path}",
        );
    }

    public function write(string $path, string $contents): void
    {
        $this->ensureDirectory(dirname($path));
        $this->attempt(
            static fn (): bool => file_put_contents($path, $contents) === strlen($contents),
            "Could not write {$path}",
        );
    }

    public function copy(string $source, string $destination): void
    {
        $this->ensureDirectory(dirname($destination));
        $this->attempt(
            static fn (): bool => copy($source, $destination),
            "Could not copy {$source} to {$destination}",
        );
    }

    /**
     * Delete a file, and throw when it stays.
     */
    public function unlink(string $path): void
    {
        $this->attempt(static fn (): bool => unlink($path), "Could not delete {$path}");
    }

    public function ensureDirectory(string $directory): void
    {
        $this->attempt(
            fn (): bool => is_dir($directory) || $this->createDirectory($directory),
            "Could not create {$directory}",
        );
    }

    /**
     * A directory another process created meanwhile counts as created.
     */
    private function createDirectory(string $directory): bool
    {
        return mkdir(
            directory: $directory,
            permissions: self::DIRECTORY_PERMISSIONS,
            recursive: true,
        ) || is_dir($directory);
    }

    /**
     * Run the operation with PHP's warning captured, and throw with its reason
     * when the operation answers false.
     *
     * @template T
     *
     * @param  callable(): T  $operation  answers false on failure
     * @return T
     */
    private function attempt(callable $operation, string $failure): mixed
    {
        $this->reason = null;

        set_error_handler($this->keepReason(...));

        try {
            $result = $operation();
        } finally {
            restore_error_handler();
        }

        return match ($result) {
            false => throw new RuntimeException($this->failure($failure)),
            default => $result,
        };
    }

    /**
     * The error handler: PHP passes the error details in its own order, and
     * only the message, the second of them, is kept.
     */
    private function keepReason(mixed ...$error): true
    {
        [, $this->reason] = $error;

        return true;
    }

    private function failure(string $failure): string
    {
        return match ($this->reason) {
            null => "{$failure}.",
            default => "{$failure}: {$this->reason}",
        };
    }
}
