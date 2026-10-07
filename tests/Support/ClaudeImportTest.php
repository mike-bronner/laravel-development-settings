<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\ClaudeImport;

beforeEach(function (): void {
    $this->project = makeTempDir('devset-claude-');
    $this->claude = "{$this->project}/CLAUDE.md";
    $this->settle = fn (array $composed = ['AGENTS.md']): ?string => (new ClaudeImport)
        ->settle($this->project, $composed);
});

afterEach(function (): void {
    removeTempDir($this->project);
});

it('writes a missing CLAUDE.md that imports AGENTS.md', function (): void {
    seedFiles($this->project, ['AGENTS.md' => agentBlock()]);

    expect(($this->settle)())->toBe(ClaudeImport::CREATED);
    expect(file_get_contents($this->claude))->toBe("@AGENTS.md\n");
});

it('writes no CLAUDE.md while there is no AGENTS.md to import', function (): void {
    expect(($this->settle)([]))->toBeNull();
    expect(file_exists($this->claude))->toBeFalse();
});

it('says a CLAUDE.md fails to import AGENTS.md, and never writes it', function (): void {
    $claude = "Ours.\n" . agentBlock();
    seedFiles($this->project, ['AGENTS.md' => agentBlock(), 'CLAUDE.md' => $claude]);

    expect(($this->settle)())->toBe(ClaudeImport::NOT_IMPORTED);
    expect(file_get_contents($this->claude))->toBe($claude);
});

it('leaves alone a CLAUDE.md that imports AGENTS.md', function (string $claude): void {
    seedFiles($this->project, ['AGENTS.md' => agentBlock(), 'CLAUDE.md' => $claude]);

    expect(($this->settle)())->toBeNull();
    expect(file_get_contents($this->claude))->toBe($claude);
})->with([
    'the import alone' => ["@AGENTS.md\n"],
    'the import among prose' => ["Team rules.\n\nRead @AGENTS.md first.\n"],
    'the import, CRLF' => ["Team rules.\r\n@AGENTS.md\r\n"],
]);

it('reports a CLAUDE.md that names AGENTS.md but does not import it', function (
    string $claude,
): void {
    seedFiles($this->project, ['AGENTS.md' => agentBlock(), 'CLAUDE.md' => $claude]);

    expect(($this->settle)())->toBe(ClaudeImport::NOT_IMPORTED);
})->with([
    'a mention' => ["See AGENTS.md.\n"],
    'another import' => ["@AGENTS.md.bak\n"],
]);

it('says nothing about a CLAUDE.md Boost composed into this run', function (): void {
    seedFiles($this->project, ['AGENTS.md' => agentBlock(), 'CLAUDE.md' => agentBlock()]);

    expect(($this->settle)(['AGENTS.md', 'CLAUDE.md']))->toBeNull();
});

it('never touches a CLAUDE.md it cannot read as a file', function (string $shape): void {
    seedFiles($this->project, ['AGENTS.md' => agentBlock(), 'elsewhere.md' => "Elsewhere.\n"]);
    match ($shape) {
        'symlink' => symlink("{$this->project}/elsewhere.md", $this->claude),
        'dangling symlink' => symlink("{$this->project}/missing.md", $this->claude),
        'directory' => mkdir($this->claude),
    };

    expect(($this->settle)())->toBeNull();
    expect(file_get_contents("{$this->project}/elsewhere.md"))->toBe("Elsewhere.\n");
})->with(['symlink', 'dangling symlink', 'directory']);

it('says so when it cannot write CLAUDE.md', function (): void {
    seedFiles($this->project, ['AGENTS.md' => agentBlock()]);
    chmod($this->project, MODE_LOCKED_DIRECTORY);

    $outcome = ($this->settle)();
    chmod($this->project, TEMP_DIR_PERMISSIONS);

    expect($outcome)->toBe(ClaudeImport::FAILED);
})->skipOnWindows();

it('says nothing about a CLAUDE.md it cannot read, and never writes it', function (): void {
    seedFiles($this->project, ['AGENTS.md' => agentBlock(), 'CLAUDE.md' => "Ours.\n"]);
    chmod($this->claude, MODE_UNREADABLE);

    $isReadable = is_readable($this->claude);
    $outcome = ($this->settle)();
    chmod($this->claude, MODE_WRITABLE);

    match ($isReadable) {
        true => test()->markTestSkipped('This user reads a 0000 file, so it cannot be staged.'),
        false => expect([$outcome, file_get_contents($this->claude)])->toBe([null, "Ours.\n"]),
    };
});
