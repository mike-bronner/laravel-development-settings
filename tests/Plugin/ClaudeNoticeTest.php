<?php

declare(strict_types=1);

use Composer\IO\BufferIO;
use MikeBronner\DevelopmentSettings\Plugin\ClaudeNotice;

beforeEach(function (): void {
    $this->project = makeTempDir('devset-claude-notice-');
    $this->output = new BufferIO;
    $this->notice = new ClaudeNotice($this->output, $this->project);
});

afterEach(function (): void {
    removeTempDir($this->project);
});

it('says it deleted CLAUDE.md, and why', function (): void {
    seedFiles($this->project, ['AGENTS.md' => agentBlock(), 'CLAUDE.md' => "@AGENTS.md\n"]);

    $this->notice
        ->settle(['AGENTS.md']);

    expect(file_exists("{$this->project}/CLAUDE.md"))->toBeFalse();
    expect($this->output->getOutput())->toContain(
            'Deleted CLAUDE.md',
            'Claude Code reads AGENTS.md only when no CLAUDE.md exists',
            'commit the deletion',
        );
});

it('says nothing when it deletes nothing', function (array $files, array $composed): void {
    seedFiles($this->project, $files);

    $this->notice
        ->settle($composed);

    expect($this->output->getOutput())->toBe('');
})->with([
    'no CLAUDE.md' => [['AGENTS.md' => agentBlock()], ['AGENTS.md']],
    'a CLAUDE.md Boost composed' => [['CLAUDE.md' => agentBlock()], ['CLAUDE.md']],
]);

it('leaves a CLAUDE.md directory and a nested CLAUDE.md alone, silently', function (): void {
    seedFiles($this->project, ['CLAUDE.md/notes.md' => "Ours.\n", 'docs/CLAUDE.md' => "Ours.\n"]);

    $this->notice
        ->settle([]);

    expect("{$this->project}/CLAUDE.md/notes.md")->toBeFile();
    expect("{$this->project}/docs/CLAUDE.md")->toBeFile();
    expect($this->output->getOutput())->toBe('');
});

it('says what to do, and why, when it cannot delete CLAUDE.md', function (): void {
    seedFiles($this->project, ['CLAUDE.md' => "Ours.\n"]);
    chmod($this->project, MODE_LOCKED_DIRECTORY);

    $this->notice
        ->settle([]);
    chmod($this->project, TEMP_DIR_PERMISSIONS);

    expect(file_get_contents("{$this->project}/CLAUDE.md"))->toBe("Ours.\n");
    expect($this->output->getOutput())->toContain(
            'CLAUDE.md could not be deleted',
            'Delete CLAUDE.md yourself.',
            "Could not delete {$this->project}/CLAUDE.md",
        );
})->skipOnWindows();
