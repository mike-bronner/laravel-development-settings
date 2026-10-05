<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\GuidelineGuard;

const BEFORE_THE_RUN = 60;

beforeEach(function (): void {
    $this->project = makeTempDir();
    $this->blocks = fn (int $since): array => (new GuidelineGuard)
        ->composedBlocks($this->project, $since);
});

afterEach(function (): void {
    removeTempDir($this->project);
});

it('passes a file Boost manages, and one never composed into', function (string $agentFile): void {
    seedFiles($this->project, ['CLAUDE.md' => $agentFile]);

    expect((new GuidelineGuard)->hazards($this->project))->toBe([]);
})->with([
    'managed by Boost' => [managedAgentFile()],
    'no opening tag, so Boost appends' => ["Nothing generated here yet.\n"],
]);

it('refuses a file whose prose names the opening tag before the block', function (): void {
    seedFiles($this->project, ['CLAUDE.md' => armedAgentFile()]);

    $hazards = (new GuidelineGuard)->hazards($this->project);

    expect(data_get($hazards, ['CLAUDE.md']))
        ->toContain('holds 2', 'between the first tag and the next closing tag');
});

it('refuses a file with no closing tag after the opening one', function (string $agentFile): void {
    seedFiles($this->project, ['AGENTS.md' => $agentFile]);

    $hazards = (new GuidelineGuard)->hazards($this->project);

    expect(data_get($hazards, ['AGENTS.md']))->toContain('no closing tag after it');
})->with([
    'never closed, so this run arms the next' => [
        'My block sits inside ' . GuidelineGuard::OPENING_TAG . " tags.\n",
    ],
    'closed only before it opens' => [
        'A stray ' . GuidelineGuard::CLOSING_TAG . ' first, then ' . GuidelineGuard::OPENING_TAG,
    ],
]);

it('examines every markdown file outside dependencies and history', function (): void {
    $package = 'vendor/mike-bronner/laravel-development-settings';
    seedFiles($this->project, [
        'CLAUDE.md' => armedAgentFile(),
        'README.md' => managedAgentFile(),
        '.github/copilot-instructions.md' => armedAgentFile(),
        '.cursor/rules/boost.mdc' => armedAgentFile(),
        'docs/handbook.markdown' => armedAgentFile(),
        "{$package}/resources/boost/g.md" => armedAgentFile(),
        'node_modules/some-package/README.md' => armedAgentFile(),
        '.git/description.md' => armedAgentFile(),
    ]);

    expect(array_keys((new GuidelineGuard)->hazards($this->project)))->toBe([
        '.cursor/rules/boost.mdc',
        '.github/copilot-instructions.md',
        'CLAUDE.md',
        'docs/handbook.markdown',
    ]);
});

it('does not read through a linked directory or a linked file', function (): void {
    $package = 'vendor/mike-bronner/laravel-development-settings';
    seedFiles($this->project, [
        "{$package}/.ai/guidelines/01.md" => armedAgentFile(),
        "{$package}/resources/boost/CLAUDE.md" => armedAgentFile(),
    ]);
    symlink("{$package}/.ai", "{$this->project}/.ai");
    symlink("{$package}/resources/boost/CLAUDE.md", "{$this->project}/CLAUDE.md");

    expect((new GuidelineGuard)->hazards($this->project))->toBe([]);
});

it('refuses a file it cannot read, since it cannot clear it', function (): void {
    seedFiles($this->project, ['CLAUDE.md' => managedAgentFile()]);
    chmod("{$this->project}/CLAUDE.md", MODE_UNREADABLE);

    $isReadable = is_readable("{$this->project}/CLAUDE.md");
    $hazards = (new GuidelineGuard)->hazards($this->project);
    chmod("{$this->project}/CLAUDE.md", MODE_WRITABLE);

    match ($isReadable) {
        true => test()->markTestSkipped('This user reads a 0000 file, so it cannot be staged.'),
        false => expect(data_get($hazards, ['CLAUDE.md']))->toContain('could not be read'),
    };
});

it('reports nothing for a directory that does not exist', function (): void {
    expect((new GuidelineGuard)->hazards("{$this->project}/absent"))->toBe([]);
});

it('sees a block composed during the run', function (): void {
    $since = time();
    seedFiles($this->project, ['AGENTS.md' => managedAgentFile()]);

    expect(array_keys(($this->blocks)($since)))->toBe(['AGENTS.md']);
});

it('does not count a block written before the run began', function (): void {
    seedFiles($this->project, ['CLAUDE.md' => managedAgentFile()]);
    touch("{$this->project}/CLAUDE.md", time() - BEFORE_THE_RUN);

    expect(($this->blocks)(time()))->toBe([]);
});

it('counts no new file without a whole block', function (string $path, string $content): void {
    $since = time();
    seedFiles($this->project, [$path => $content]);

    expect(($this->blocks)($since))->toBe([]);
})->with([
    'no tags' => ['AGENTS.md', "No tags.\n"],
    'opening tag only' => ['AGENTS.md', GuidelineGuard::OPENING_TAG . "\n"],
    'closing tag before opening tag' => [
        'AGENTS.md',
        GuidelineGuard::CLOSING_TAG . "\n" . GuidelineGuard::OPENING_TAG . "\n",
    ],
    'a block inside vendor' => ['vendor/acme/pkg/AGENTS.md', agentBlock()],
]);

it('answers each composed block without the prose around it, in path order', function (): void {
    $since = time();
    $unclosed = fn (string $rules): string => str_replace(
            GuidelineGuard::CLOSING_TAG . "\n",
            '',
            agentBlock($rules),
        );
    seedFiles($this->project, [
        'CLAUDE.md' => agentFile('Prose before.') . "Prose after.\n",
        'AGENTS.md' => agentBlock('first') . agentBlock('second'),
    ]);

    expect(($this->blocks)($since))->toBe([
        'AGENTS.md' => $unclosed('first'),
        'CLAUDE.md' => $unclosed('foundation rules'),
    ]);
});
