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

it('says it wrote CLAUDE.md as an import of AGENTS.md', function (): void {
    seedFiles($this->project, ['AGENTS.md' => agentBlock()]);

    $this->notice
        ->settle(['AGENTS.md']);

    expect(file_get_contents("{$this->project}/CLAUDE.md"))->toBe("@AGENTS.md\n");
    expect($this->output->getOutput())
        ->toContain('Wrote CLAUDE.md, which imports AGENTS.md', 'Commit it.');
});

it('tells a CLAUDE.md that does not import AGENTS.md what to add', function (): void {
    seedFiles($this->project, ['AGENTS.md' => agentBlock(), 'CLAUDE.md' => agentBlock()]);

    $this->notice
        ->settle(['AGENTS.md']);

    expect(file_get_contents("{$this->project}/CLAUDE.md"))->toBe(agentBlock());
    expect($this->output->getOutput())->toContain(
            'CLAUDE.md does not import AGENTS.md',
            "Add the line \"@AGENTS.md\" to CLAUDE.md",
        );
});

it('does nothing after a Boost run that did not compose cleanly', function (): void {
    seedFiles($this->project, ['AGENTS.md' => agentBlock()]);

    $this->notice
        ->settle(null);

    expect(file_exists("{$this->project}/CLAUDE.md"))->toBeFalse();
    expect($this->output->getOutput())->toBe('');
});

it('says nothing when CLAUDE.md already imports AGENTS.md', function (): void {
    seedFiles($this->project, ['AGENTS.md' => agentBlock(), 'CLAUDE.md' => "@AGENTS.md\n"]);

    $this->notice
        ->settle(['AGENTS.md']);

    expect($this->output->getOutput())->toBe('');
});

it('says what to do when it cannot write CLAUDE.md', function (): void {
    seedFiles($this->project, ['AGENTS.md' => agentBlock()]);
    chmod($this->project, MODE_LOCKED_DIRECTORY);

    $this->notice
        ->settle(['AGENTS.md']);
    chmod($this->project, TEMP_DIR_PERMISSIONS);

    expect($this->output->getOutput())
        ->toContain('CLAUDE.md could not be written', "Create it with the line \"@AGENTS.md\".");
})->skipOnWindows();
