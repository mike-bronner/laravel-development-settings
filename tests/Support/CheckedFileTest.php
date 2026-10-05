<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\CheckedFile;

beforeEach(function (): void {
    $this->dir = makeTempDir();
    file_put_contents("{$this->dir}/a.txt", "a\n");
});

afterEach(function (): void {
    removeTempDir($this->dir);
});

it('reads a file', function (): void {
    expect((new CheckedFile)->read("{$this->dir}/a.txt"))->toBe("a\n");
});

it('throws with the path and the reason PHP gave when a file cannot be read', function (): void {
    expect(fn () => (new CheckedFile)->read("{$this->dir}/missing.txt"))
        ->toThrow(RuntimeException::class, "Could not read {$this->dir}/missing.txt: ");
});

it('writes and copies, creating the directories they need', function (string $operation): void {
    $file = new CheckedFile;

    match ($operation) {
        'write' => $file->write("{$this->dir}/b/c/a.txt", "a\n"),
        'copy' => $file->copy("{$this->dir}/a.txt", "{$this->dir}/b/c/a.txt"),
    };

    expect(file_get_contents("{$this->dir}/b/c/a.txt"))->toBe("a\n");
})->with(['write', 'copy']);

it('throws when a file cannot be written, and leaves it as it was', function (): void {
    chmod("{$this->dir}/a.txt", MODE_READ_ONLY);

    expect(fn () => (new CheckedFile)->write("{$this->dir}/a.txt", "b\n"))
        ->toThrow(RuntimeException::class, 'Permission denied')
        ->and(file_get_contents("{$this->dir}/a.txt"))
        ->toBe("a\n");

    chmod("{$this->dir}/a.txt", MODE_WRITABLE);
});

it('throws when a directory cannot be created, naming it', function (): void {
    mkdir("{$this->dir}/locked", MODE_LOCKED_DIRECTORY);

    expect(fn () => (new CheckedFile)->copy("{$this->dir}/a.txt", "{$this->dir}/locked/new/a"))
        ->toThrow(RuntimeException::class, "Could not create {$this->dir}/locked/new: ");

    chmod("{$this->dir}/locked", TEMP_DIR_PERMISSIONS);
});

it('accepts a directory that already exists', function (): void {
    (new CheckedFile)->ensureDirectory($this->dir);

    expect(is_dir($this->dir))->toBeTrue();
});

it('unlinks a file, and throws when there is none to unlink', function (): void {
    $file = new CheckedFile;

    $file->unlink("{$this->dir}/a.txt");

    expect(file_exists("{$this->dir}/a.txt"))->toBeFalse()
        ->and(fn () => $file->unlink("{$this->dir}/a.txt"))
        ->toThrow(RuntimeException::class, "Could not delete {$this->dir}/a.txt: ");
});

it('puts back the error handler it replaced, after a failure too', function (): void {
    $handler = static fn (): bool => false;
    set_error_handler($handler);

    expect(fn () => (new CheckedFile)->read("{$this->dir}/missing.txt"))
        ->toThrow(RuntimeException::class);

    $current = set_error_handler(null);
    restore_error_handler();
    restore_error_handler();

    expect($current)->toBe($handler);
});
