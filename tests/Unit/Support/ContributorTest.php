<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\Contributor;
use MikeBronner\DevelopmentSettings\Support\Process;

/**
 * Records commands and returns exit codes by substring match (default 0).
 */
final class RecordingProcess implements Process
{
    /** @var list<string> */
    public array $commands = [];

    /**
     * @param  array<string, int>  $exitCodes  command substring => exit code
     */
    public function __construct(private array $exitCodes = [], private int $default = 0) {}

    public function run(string $command, ?string $workingDirectory = null): int
    {
        $this->commands[] = $command;

        foreach ($this->exitCodes as $needle => $code) {
            if (str_contains($command, $needle)) {
                return $code;
            }
        }

        return $this->default;
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
        ['.ai/guidelines/edited.md' => $source . '/edited.md'],
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

it('does not embed the token in the gh/commit commands but uses it for clone', function (): void {
    $source = makeTempDir();
    file_put_contents($source . '/x.md', 'c');
    $process = new RecordingProcess(exitCodes: ['diff --cached --quiet' => 1]);

    (new Contributor($process))->open(
        ['.ai/x.md' => $source . '/x.md'],
        'contribute/test',
        makeTempDir() . '/clone',
        token: 'SECRET',
    );

    $cloneCmd = $process->commands[0];
    expect($cloneCmd)->toContain('x-access-token:SECRET@github.com');

    removeTempDir($source);
});

it('aborts when nothing differs from upstream', function (): void {
    $source = makeTempDir();
    file_put_contents($source . '/x.md', 'c');

    // diff --cached --quiet returns 0 == "no staged changes".
    $process = new RecordingProcess(exitCodes: ['diff --cached --quiet' => 0]);

    $result = (new Contributor($process))->open(
        ['.ai/x.md' => $source . '/x.md'],
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
        ['.ai/x.md' => '/tmp/whatever.md'],
        'contribute/test',
        '/tmp/clone-fail',
    );

    expect($result['status'])->toBe(1)
        ->and($result['message'])->toContain('Failed to clone')
        ->and($process->commands)->toHaveCount(1);
});
