<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\OrphanRemover;

beforeEach(function (): void {
    $this->project = makeTempDir();
});

afterEach(function (): void {
    removeTempDir($this->project);
});

it('removes the file and every directory it leaves empty, up to the project', function (): void {
    seedFiles($this->project, ['a/b/c/orphan.md' => 'x', 'a/kept.md' => 'x']);
    set_error_handler(static fn (int $level, string $warning): never => throw new ErrorException(
            $warning,
            severity: $level,
        ));

    try {
        (new OrphanRemover())->remove($this->project, 'a/b/c/orphan.md');
    } finally {
        restore_error_handler();
    }

    expect([is_dir("{$this->project}/a/b"), file_exists("{$this->project}/a/kept.md")])
        ->toBe([false, true]);
});

it('removes a file at the project root and leaves the project itself', function (): void {
    seedFiles($this->project, ['orphan.md' => 'x']);

    (new OrphanRemover())->remove($this->project, 'orphan.md');

    expect([file_exists("{$this->project}/orphan.md"), is_dir($this->project)])
        ->toBe([false, true]);
});

it('tidies the empty directories of a file already gone', function (): void {
    mkdir("{$this->project}/a/b", recursive: true);

    (new OrphanRemover())->remove($this->project, 'a/b/orphan.md');

    expect(is_dir("{$this->project}/a"))->toBeFalse();
});
