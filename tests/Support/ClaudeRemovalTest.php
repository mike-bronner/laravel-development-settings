<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\ClaudeRemoval;

beforeEach(function (): void {
    $this->project = makeTempDir('devset-claude-removal-');
    $this->claude = "{$this->project}/CLAUDE.md";
    $this->isRemovable = fn (array $composed = ['AGENTS.md']): bool => (new ClaudeRemoval)
        ->isRemovable($this->project, $composed);
    $this->remove = fn () => (new ClaudeRemoval)->remove($this->project);
});

afterEach(function (): void {
    removeTempDir($this->project);
});

it('removes a CLAUDE.md, whatever it holds', function (string $claude): void {
    seedFiles($this->project, ['AGENTS.md' => agentBlock(), 'CLAUDE.md' => $claude]);

    expect(($this->isRemovable)())->toBeTrue();
    ($this->remove)();
    expect(file_exists($this->claude))->toBeFalse();
    expect(file_get_contents("{$this->project}/AGENTS.md"))->toBe(agentBlock());
})->with([
    'an import of AGENTS.md' => ["@AGENTS.md\n"],
    'a stale Boost block' => [agentBlock()],
    'hand-written notes' => ["Ours.\n"],
    'nothing' => [''],
]);

it('removes a CLAUDE.md when Boost composed nothing, or only other files', function (
    array $composed,
): void {
    seedFiles($this->project, ['CLAUDE.md' => "Ours.\n"]);

    expect(($this->isRemovable)($composed))->toBeTrue();
})->with([
    'nothing composed' => [[]],
    'a nested CLAUDE.md composed' => [['docs/CLAUDE.md']],
]);

it('keeps a CLAUDE.md Boost composed during this run', function (): void {
    seedFiles($this->project, ['AGENTS.md' => agentBlock(), 'CLAUDE.md' => agentBlock()]);

    expect(($this->isRemovable)(['AGENTS.md', 'CLAUDE.md']))->toBeFalse();
});

it('has nothing to remove without a root CLAUDE.md', function (): void {
    seedFiles($this->project, [
        '.claude/CLAUDE.md' => "Ours.\n",
        'CLAUDE.local.md' => "Ours.\n",
        'docs/CLAUDE.md' => "Ours.\n",
    ]);

    expect(($this->isRemovable)())->toBeFalse();
});

it('removes a CLAUDE.md link, and never its target', function (string $target): void {
    seedFiles($this->project, ['elsewhere.md' => "Elsewhere.\n", 'elsewhere/kept.md' => "Kept.\n"]);
    symlink("{$this->project}/{$target}", $this->claude);

    expect(($this->isRemovable)())->toBeTrue();
    ($this->remove)();
    expect(is_link($this->claude))->toBeFalse();
    expect(file_get_contents("{$this->project}/elsewhere.md"))->toBe("Elsewhere.\n");
    expect(file_get_contents("{$this->project}/elsewhere/kept.md"))->toBe("Kept.\n");
})->with([
    'a link to a file' => ['elsewhere.md'],
    'a link to a directory' => ['elsewhere'],
    'a dangling link' => ['missing.md'],
])->skipOnWindows();

it('leaves a CLAUDE.md directory alone', function (): void {
    seedFiles($this->project, ['CLAUDE.md/notes.md' => "Ours.\n"]);

    expect(($this->isRemovable)())->toBeFalse();
});

it('throws with the cause when it cannot remove CLAUDE.md', function (): void {
    seedFiles($this->project, ['CLAUDE.md' => "Ours.\n"]);
    chmod($this->project, MODE_LOCKED_DIRECTORY);

    try {
        ($this->remove)();
    } finally {
        chmod($this->project, TEMP_DIR_PERMISSIONS);
    }
})->throws(RuntimeException::class, 'Could not delete')
    ->skipOnWindows();
