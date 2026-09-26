<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\SystemProcess;

function phpCommand(string $script): string
{
    return escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script);
}

it('captures stdout and stderr together, in the order they were written, with the exit code', function (): void {
    $result = (new SystemProcess)->capture(phpCommand(
        'echo "one\n"; fflush(STDOUT); fwrite(STDERR, "two\n"); echo "three\n"; exit(4);',
    ));

    expect($result->exitCode)->toBe(4)
        ->and($result->failed())->toBeTrue()
        ->and($result->output)->toBe("one\ntwo\nthree\n");
});

it('answers only the exit code through run()', function (): void {
    $process = new SystemProcess;

    expect($process->run(phpCommand('fwrite(STDERR, "noise"); exit(0);')))->toBe(0)
        ->and($process->run(phpCommand('exit(3);')))->toBe(3);
});

it('runs in the working directory and passes extra environment variables to the command only', function (): void {
    $directory = makeTempDir();

    $result = (new SystemProcess)->capture(
        phpCommand('echo getcwd(), "|", getenv("DEVSET_PROBE");'),
        $directory,
        ['DEVSET_PROBE' => 'reached'],
    );

    expect($result->output)->toBe(realpath($directory) . '|reached')
        ->and(getenv('DEVSET_PROBE'))->toBeFalse();

    removeTempDir($directory);
});

it('drains a child that writes more to stderr than a pipe buffer holds, without deadlocking', function (): void {
    // The child writes 1 MiB to stderr before anything to stdout. A parent that
    // reads stdout to its end first never reads stderr, so the child blocks on
    // a full pipe for good. The child gives up after five seconds and exits 3,
    // so a regression fails this test instead of hanging the suite.
    $script = <<<'PHP'
        $data = str_repeat('e', 1048576);
        $offset = 0;
        $deadline = microtime(true) + 5;
        stream_set_blocking(STDERR, false);
        while ($offset < strlen($data)) {
            $written = @fwrite(STDERR, substr($data, $offset, 65536));
            if ($written) {
                $offset += $written;
            } elseif (microtime(true) > $deadline) {
                exit(3);
            } else {
                usleep(1000);
            }
        }
        echo 'done';
        PHP;

    $result = (new SystemProcess)->capture(phpCommand($script));

    expect($result->exitCode)->toBe(0)
        ->and(strlen($result->output))->toBe(1_048_576 + 4)
        ->and($result->output)->toEndWith('done');
});
