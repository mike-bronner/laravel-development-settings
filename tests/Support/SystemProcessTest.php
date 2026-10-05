<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\SystemProcess;

const ONE_MEBIBYTE = 1_048_576;

const MIXED_EXIT = 4;

const OTHER_EXIT = 3;

beforeEach(function (): void {
    $this->directory = makeTempDir();
});

afterEach(function (): void {
    removeTempDir($this->directory);
});

it('captures stdout and stderr together, in the order written, and the exit', function (): void {
    $script = <<<PHP
        echo 'one', PHP_EOL; fflush(STDOUT); fwrite(STDERR, 'two' . PHP_EOL); echo 'three'; exit(4);
        PHP;

    $result = (new SystemProcess)->capture(phpCommand($script));

    expect([$result->exitCode(), $result->failed(), $result->output()])
        ->toBe([MIXED_EXIT, true, "one\ntwo\nthree"]);
});

it('answers only the exit code through run()', function (): void {
    $process = new SystemProcess;

    expect($process->run(phpCommand('fwrite(STDERR, PHP_EOL); exit(0);')))->toBe(0)
        ->and($process->run(phpCommand('exit(3);')))
        ->toBe(OTHER_EXIT);
});

it('runs in the working directory', function (): void {
    $result = (new SystemProcess)->capture(phpCommand('echo getcwd();'), $this->directory);

    expect($result->output())->toBe(realpath($this->directory));
});

it('drains a child that fills the stderr pipe first, without deadlock', function (): void {
    $script = <<<PHP
        \$data = str_repeat('e', 1048576); \$offset = 0; \$deadline = microtime(true) + 5;
        stream_set_blocking(STDERR, false);
        while (\$offset < strlen(\$data)) {
            \$written = @fwrite(STDERR, substr(\$data, \$offset, 65536));
            \$offset += (int) \$written;
            \$written || microtime(true) <= \$deadline || exit(3);
            \$written || usleep(1000);
        }
        echo 'done';
        PHP;

    $result = (new SystemProcess)->capture(phpCommand($script));

    expect($result->exitCode())->toBe(0)
        ->and(strlen($result->output()))
        ->toBe(ONE_MEBIBYTE + strlen('done'))
        ->and($result->output())
        ->toEndWith('done');
});

it('runs passthru on the stdin, stdout and stderr of this process', function (): void {
    $identity = static function (mixed $stream): string {
        $stat = fstat($stream);

        return data_get($stat, 'dev') . ':' . data_get($stat, 'ino');
    };
    $streams = implode(',', [$identity(STDIN), $identity(STDOUT), $identity(STDERR)]);
    $expected = var_export($streams, true);
    $script = <<<PHP
        \$id = fn (\$stream) => fstat(\$stream)['dev'] . ':' . fstat(\$stream)['ino'];
        exit(implode(',', [\$id(STDIN), \$id(STDOUT), \$id(STDERR)]) === {$expected} ? 0 : 5);
        PHP;
    $process = new SystemProcess;

    expect($process->passthru(phpCommand($script)))->toBe(0)
        ->and($process->passthru(phpCommand('exit(3);')))
        ->toBe(OTHER_EXIT);
});

it('runs a passthru command in the working directory', function (): void {
    $directory = var_export(realpath($this->directory), true);

    $exitCode = (new SystemProcess)
        ->passthru(phpCommand("exit(getcwd() === {$directory} ? 0 : 6);"), $this->directory);

    expect($exitCode)->toBe(0);
});

it('reports a command it could not start, as a failure', function (): void {
    set_error_handler(static fn (): bool => true);

    try {
        $result = (new SystemProcess)->capture('true', "{$this->directory}/missing");
    } finally {
        restore_error_handler();
    }

    expect([$result->exitCode(), $result->output()])->toBe([1, 'Could not start: true']);
});
