<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\Contributor;
use MikeBronner\DevelopmentSettings\Tests\Fixtures\RecordingProcess;

const STAGED_CHANGES = ['diff --cached --quiet' => 1];

const STEPS_AFTER_THE_CLONE = 6;

beforeEach(function (): void {
    $this->source = makeTempDir();
    $this->cloneDir = makeTempDir() . '/clone';
    seedFiles($this->source, ['edited.md' => 'guideline fixed', 'SKILL.md' => 'skill fixed']);
    $this->edits = ['resources/boost/guidelines/01-identity.md' => "{$this->source}/edited.md"];
});

afterEach(function (): void {
    removeTempDir($this->source);
    removeTempDir(dirname($this->cloneDir));
});

it('does nothing when there are no modified files', function (): void {
    $process = new RecordingProcess;

    $result = (new Contributor($process))->open([], 'contribute/x', $this->cloneDir);

    expect($result)->toBe(['status' => 0, 'message' => 'Nothing to contribute.'])
        ->and($process->commands())
        ->toBe([]);
});

it('clones, branches, adds, commits, pushes and opens the pr, in order', function (): void {
    $process = new RecordingProcess(exitCodes: STAGED_CHANGES);

    $result = (new Contributor($process))
        ->open($this->edits, 'contribute/test', $this->cloneDir, token: 'secret');

    expect($result)->toBe([
        'status' => 0,
        'message' => 'Opened a contribution PR from branch contribute/test.',
    ])
        ->and($process->log())
        ->toMatch(
            '/git clone --depth 1.*\n.*git checkout -b.*\n.*git add -A.*'
                . '\n.*git diff --cached --quiet.*\n.*git commit -m.*\n.*git push -u origin.*'
                . '\n.*gh pr create.*mike-bronner\/laravel-development-settings/s',
        )
        ->and(is_dir($this->cloneDir))
        ->toBeFalse();
});

it('lands each edited source at its resources/boost path in the clone', function (): void {
    $process = new RecordingProcess(exitCodes: STAGED_CHANGES);

    (new Contributor($process))->open([
        ...$this->edits,
        'resources/boost/skills/laravel/SKILL.md' => "{$this->source}/SKILL.md",
    ], 'contribute/test', $this->cloneDir);

    expect($process->stagedTree())->toBe([
        'resources/boost/guidelines/01-identity.md' => 'guideline fixed',
        'resources/boost/skills/laravel/SKILL.md' => 'skill fixed',
    ]);
});

it('uses the token for the clone and keeps it out of every later command', function (): void {
    $process = new RecordingProcess(exitCodes: STAGED_CHANGES);

    $result = (new Contributor($process))
        ->open($this->edits, 'contribute/test', $this->cloneDir, token: 'SECRET');
    $commands = $process->commands();
    $clone = (string) reset($commands);
    $later = array_slice($commands, 1);

    expect(data_get($result, 'status'))->toBe(0)
        ->and($clone)
        ->toContain('x-access-token:SECRET@github.com')
        ->and($later)
        ->toHaveCount(STEPS_AFTER_THE_CLONE)
        ->and(implode("\n", $later))
        ->not
        ->toContain('SECRET');
});

it('ends as a no-op when nothing differs from upstream', function (): void {
    $process = new RecordingProcess(exitCodes: ['diff --cached --quiet' => 0]);

    $result = (new Contributor($process))->open($this->edits, 'contribute/test', $this->cloneDir);

    expect($result)->toBe([
        'status' => 0,
        'message' => 'No changes versus upstream — nothing to contribute.',
    ])
        ->and($process->log())
        ->not
        ->toContain('git commit');
});

it('stops at the step that fails, and says which', function (string $step, string $message): void {
    $process = new RecordingProcess(exitCodes: [...STAGED_CHANGES, $step => 1]);

    $result = (new Contributor($process))->open($this->edits, 'contribute/test', $this->cloneDir);
    $commands = $process->commands();

    expect($result)->toBe(['status' => 1, 'message' => $message])
        ->and(end($commands))
        ->toContain($step);
})->with([
    'clone' => ['git clone', 'Failed to clone mike-bronner/laravel-development-settings.'],
    'branch' => ['git checkout', 'Failed to create branch contribute/test.'],
    'commit' => ['git commit', 'Failed to commit changes.'],
    'push' => ['git push', 'Failed to push branch contribute/test.'],
    'pull request' => [
        'gh pr create',
        'Pushed contribute/test but failed to open the PR (open it manually).',
    ],
]);

it('names the branch after the project and the time', function (): void {
    expect((new Contributor(new RecordingProcess))->branchFor('/work/My App'))
        ->toMatch('/^contribute\/My-App-\d{14}$/');
});

it('clones into a fresh directory each time', function (): void {
    $contributor = new Contributor(new RecordingProcess);

    expect($contributor->cloneDirectory())->toStartWith(sys_get_temp_dir() . '/devset-contribute-')
        ->not
        ->toBe($contributor->cloneDirectory());
});
