<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\GuidelineGuard;

/**
 * The shape Boost leaves behind: one opening tag, generated content, one
 * closing tag, and the project's own writing around it.
 */
function managedAgentFile(): string
{
    return "# CLAUDE.md\n\nProject notes.\n\n"
        . GuidelineGuard::OPENING_TAG . "\n=== foundation rules ===\n\n" . GuidelineGuard::CLOSING_TAG . "\n";
}

/**
 * The shape that destroys work: a hand-written section names the opening tag in
 * prose, and the real block follows it.
 */
function armedAgentFile(): string
{
    return "# CLAUDE.md\n\nBoost replaces the block between " . GuidelineGuard::OPENING_TAG . " and its closing tag.\n\n"
        . GuidelineGuard::OPENING_TAG . "\n=== foundation rules ===\n\n" . GuidelineGuard::CLOSING_TAG . "\n";
}

function writeProjectFile(string $projectDir, string $relativePath, string $content): void
{
    $path = $projectDir . '/' . $relativePath;

    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0755, true);
    }

    file_put_contents($path, $content);
}

it('passes an agent file Boost already manages', function (): void {
    $project = makeTempDir();
    writeProjectFile($project, 'CLAUDE.md', managedAgentFile());

    expect((new GuidelineGuard)->hazards($project))->toBe([]);

    removeTempDir($project);
});

it('passes a file that has never been composed into', function (): void {
    $project = makeTempDir();
    writeProjectFile($project, 'CLAUDE.md', "# CLAUDE.md\n\nNothing generated here yet.\n");

    // No opening tag at all: Boost appends its block and destroys nothing.
    expect((new GuidelineGuard)->hazards($project))->toBe([]);

    removeTempDir($project);
});

it('refuses a file whose prose names the opening tag before the real block', function (): void {
    $project = makeTempDir();
    writeProjectFile($project, 'CLAUDE.md', armedAgentFile());

    $hazards = (new GuidelineGuard)->hazards($project);

    expect($hazards)->toHaveKey('CLAUDE.md')
        ->and($hazards['CLAUDE.md'])->toContain('holds 2')
        ->and($hazards['CLAUDE.md'])->toContain('between the first tag and the next closing tag');

    removeTempDir($project);
});

it('refuses a file whose single opening tag is never closed', function (): void {
    $project = makeTempDir();

    // Composing here succeeds and appends a real block, which leaves two
    // opening tags. The run looks clean and arms the one after it.
    writeProjectFile($project, 'AGENTS.md', "# AGENTS.md\n\nMy block sits inside " . GuidelineGuard::OPENING_TAG . " tags.\n");

    expect((new GuidelineGuard)->hazards($project)['AGENTS.md'] ?? null)
        ->toContain('no closing tag after it');

    removeTempDir($project);
});

it('refuses a file whose only closing tag comes before its opening tag', function (): void {
    $project = makeTempDir();

    // Counting closing tags would clear this file. Boost's pattern needs the
    // closing tag *after* the opening one, so it appends here as well.
    writeProjectFile($project, 'AGENTS.md', "A stray " . GuidelineGuard::CLOSING_TAG . " first.\n\nThen " . GuidelineGuard::OPENING_TAG . " opens.\n");

    expect((new GuidelineGuard)->hazards($project)['AGENTS.md'] ?? null)
        ->toContain('no closing tag after it');

    removeTempDir($project);
});

it('examines agent files below the project root, whatever the agent', function (): void {
    $project = makeTempDir();
    writeProjectFile($project, '.github/copilot-instructions.md', armedAgentFile());
    writeProjectFile($project, '.cursor/rules/boost.mdc', armedAgentFile());
    writeProjectFile($project, 'docs/handbook.markdown', armedAgentFile());

    // Boost maps agents to paths upstream, and every path is overridable in
    // config. Reading the markdown tree cannot drift out of step with it.
    expect(array_keys((new GuidelineGuard)->hazards($project)))->toBe([
        '.cursor/rules/boost.mdc',
        '.github/copilot-instructions.md',
        'docs/handbook.markdown',
    ]);

    removeTempDir($project);
});

it('ignores dependency and history directories', function (): void {
    $project = makeTempDir();
    writeProjectFile($project, 'vendor/mikebronner/development-settings/resources/boost/g.md', armedAgentFile());
    writeProjectFile($project, 'node_modules/some-package/README.md', armedAgentFile());
    writeProjectFile($project, '.git/description.md', armedAgentFile());

    expect((new GuidelineGuard)->hazards($project))->toBe([]);

    removeTempDir($project);
});

it('does not read through a symlink into vendor', function (): void {
    $project = makeTempDir();
    $package = 'vendor/mikebronner/development-settings';
    writeProjectFile($project, $package . '/.ai/guidelines/01.md', armedAgentFile());
    writeProjectFile($project, $package . '/resources/boost/CLAUDE.md', armedAgentFile());

    // A linked directory, as earlier releases of this package created, and a
    // linked agent file. Either one names a path inside a dependency, which the
    // developer cannot edit and Boost's own composition does not own.
    symlink($package . '/.ai', $project . '/.ai');
    symlink($package . '/resources/boost/CLAUDE.md', $project . '/CLAUDE.md');

    expect((new GuidelineGuard)->hazards($project))->toBe([]);

    removeTempDir($project);
});

it('refuses a file it cannot read', function (): void {
    $project = makeTempDir();
    writeProjectFile($project, 'CLAUDE.md', managedAgentFile());
    chmod($project . '/CLAUDE.md', 0000);

    if (is_readable($project . '/CLAUDE.md')) {
        chmod($project . '/CLAUDE.md', 0644);
        removeTempDir($project);

        test()->markTestSkipped('This user can read a 0000 file, so unreadability cannot be staged.');
    }

    // A file this package cannot inspect is one it cannot clear.
    expect((new GuidelineGuard)->hazards($project)['CLAUDE.md'] ?? null)
        ->toContain('could not be read');

    chmod($project . '/CLAUDE.md', 0644);
    removeTempDir($project);
});

it('reports every damaged file', function (): void {
    $project = makeTempDir();
    writeProjectFile($project, 'CLAUDE.md', armedAgentFile());
    writeProjectFile($project, 'AGENTS.md', armedAgentFile());
    writeProjectFile($project, 'README.md', managedAgentFile());

    expect(array_keys((new GuidelineGuard)->hazards($project)))->toBe(['AGENTS.md', 'CLAUDE.md']);

    removeTempDir($project);
});

it('reports nothing for a directory that does not exist', function (): void {
    $project = makeTempDir();

    expect((new GuidelineGuard)->hazards($project . '/absent'))->toBe([]);

    removeTempDir($project);
});
