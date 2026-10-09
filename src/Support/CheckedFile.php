<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use RuntimeException;

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

    private function createDirectory(string $directory): bool
    {
        return mkdir(
                directory: $directory,
                permissions: self::DIRECTORY_PERMISSIONS,
                recursive: true,
            ) || is_dir($directory);
    }

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
