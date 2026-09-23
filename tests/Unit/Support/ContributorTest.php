<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\Contributor;
use MikeBronner\DevelopmentSettings\Support\Process;

/**
 * Records commands and returns exit codes by substring match (default 0).
 *
 * When `git add` runs, it also records every file in the working directory
 * with its contents. The clone is deleted before `open()` returns, so this
 * snapshot is the only evidence of where each edited file landed.
 */
final class RecordingProcess implements Process
{
    /** @var list<string> */
    public array $commands = [];

    /** @var array<string, string>|null relative path => contents, taken at `git add` */
    public ?array $stagedTree = null;

    /**
     * @param  array<string, int>  $exitCodes  command substring => exit code
     */
    public function __construct(private array $exitCodes = [], private int $default = 0) {}

    public function run(string $command, ?string $workingDirectory = null): int
    {
        $this->commands[] = $command;

        if (str_starts_with($command, 'git add') && $workingDirectory !== null) {
            $this->stagedTree = $this->treeOf($workingDirectory);
        }

        foreach ($this->exitCodes as $needle => $code) {
            if (str_contains($command, $needle)) {
                return $code;
            }
        }

        return $this->default;
    }

    /**
     * @return array<string, string>
     */
    private function treeOf(string $directory): array
    {
        $tree = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            $tree[substr($file->getPathname(), strlen($directory) + 1)] = (string) file_get_contents($file->getPathname());
        }

        ksort($tree);

        return $tree;
    }
}

function joined(RecordingProcess $p): string
{
    return implode("\n", $p->commands);
}

it('does nothing when there are no modified files', function (): void {
    $process = new RecordingProcess;

    $result = (new Contributor($process))->open([], 'contribute/x', '/tmp/none');

    expect($result['status'])->toBe(0)
        ->and($result['message'])->toContain('Nothing to contribute')
        ->and($process->commands)->toBe([]);
});

it('runs clone → branch → add → commit → push → pr in order', function (): void {
    $source = makeTempDir();
    file_put_contents($source . '/edited.md', 'changed');

    // diff --cached --quiet returns 1 == "there are staged changes".
    $process = new RecordingProcess(exitCodes: ['diff --cached --quiet' => 1]);
    $cloneDir = makeTempDir() . '/clone';

    $result = (new Contributor($process))->open(
        ['resources/boost/guidelines/edited.md' => $source . '/edited.md'],
        'contribute/test',
        $cloneDir,
        token: 'secrettoken',
    );

    $log = joined($process);

    expect($result['status'])->toBe(0)
        ->and($log)->toContain('git clone --depth 1')
        ->and($log)->toContain('git checkout -b')
        ->and($log)->toContain('git add -A')
        ->and($log)->toContain('git commit -m')
        ->and($log)->toContain('git push -u origin')
        ->and($log)->toContain('gh pr create')
        ->and($log)->toContain('mikebronner/development-settings');

    // Clone dir is cleaned up afterwards.
    expect(is_dir($cloneDir))->toBeFalse();

    removeTempDir($source);
});

it('lands each edited source at its resources/boost path in the clone, and nowhere else', function (): void {
    $source = makeTempDir();
    file_put_contents($source . '/edited.md', 'guideline fixed in a project');
    file_put_contents($source . '/SKILL.md', 'skill fixed in a project');
    $process = new RecordingProcess(exitCodes: ['diff --cached --quiet' => 1]);
    $cloneDir = makeTempDir() . '/clone';

    (new Contributor($process))->open(
        [
            'resources/boost/guidelines/01-identity.md' => $source . '/edited.md',
            'resources/boost/skills/laravel/SKILL.md' => $source . '/SKILL.md',
        ],
        'contribute/test',
        $cloneDir,
    );

    // The fake clone starts empty, so the staged tree holds exactly what the
    // contribution copied in. A file at any other path fails the comparison.
    expect($process->stagedTree)->toBe([
        'resources/boost/guidelines/01-identity.md' => 'guideline fixed in a project',
        'resources/boost/skills/laravel/SKILL.md' => 'skill fixed in a project',
    ]);

    removeTempDir($source);
});

it('uses the token for the clone and keeps it out of every later command', function (): void {
    $source = makeTempDir();
    file_put_contents($source . '/x.md', 'c');
    $process = new RecordingProcess(exitCodes: ['diff --cached --quiet' => 1]);

    $result = (new Contributor($process))->open(
        ['resources/boost/x.md' => $source . '/x.md'],
        'contribute/test',
        makeTempDir() . '/clone',
        token: 'SECRET',
    );

    [$clone, $later] = [$process->commands[0], array_slice($process->commands, 1)];

    // Every step ran, so the check below covers checkout, add, diff, commit,
    // push and the PR, not an early exit.
    expect($result['status'])->toBe(0)
        ->and($clone)->toContain('x-access-token:SECRET@github.com')
        ->and($later)->toHaveCount(6)
        ->and(array_filter($later, fn (string $command): bool => str_contains($command, 'SECRET')))->toBe([]);

    removeTempDir($source);
});

it('aborts when nothing differs from upstream', function (): void {
    $source = makeTempDir();
    file_put_contents($source . '/x.md', 'c');

    // diff --cached --quiet returns 0 == "no staged changes".
    $process = new RecordingProcess(exitCodes: ['diff --cached --quiet' => 0]);

    $result = (new Contributor($process))->open(
        ['resources/boost/x.md' => $source . '/x.md'],
        'contribute/test',
        makeTempDir() . '/clone',
    );

    expect($result['status'])->toBe(0)
        ->and($result['message'])->toContain('No changes')
        ->and(joined($process))->not->toContain('git commit');

    removeTempDir($source);
});

it('reports a clear error when the clone fails', function (): void {
    $process = new RecordingProcess(exitCodes: ['git clone' => 1]);

    $result = (new Contributor($process))->open(
        ['resources/boost/x.md' => '/tmp/whatever.md'],
        'contribute/test',
        '/tmp/clone-fail',
    );

    expect($result['status'])->toBe(1)
        ->and($result['message'])->toContain('Failed to clone')
        ->and($process->commands)->toHaveCount(1);
});
