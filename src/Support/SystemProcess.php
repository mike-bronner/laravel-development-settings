<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use Override;

final class SystemProcess implements Process
{
    private const INPUT = 0;

    private const OUTPUT = 1;

    private const ERRORS = 2;

    private const FAILED_TO_START = 1;

    #[Override]
    public function run(string $command, ?string $workingDirectory = null): int
    {
        $result = $this->capture($command, $workingDirectory);

        return $result->exitCode();
    }

    /**
     * Run a command with its output captured.
     *
     * Stderr is redirected into the stdout pipe, so the output keeps the order
     * it was written in, and there is only one pipe to drain. Two pipes read
     * one after the other deadlock once the child fills the one not being
     * read.
     */
    public function capture(string $command, ?string $workingDirectory = null): ProcessResult
    {
        $process = proc_open(
            $command,
            [
                self::INPUT => ['pipe', 'r'],
                self::OUTPUT => ['pipe', 'w'],
                self::ERRORS => ['redirect', self::OUTPUT],
            ],
            $pipes,
            $workingDirectory ?? getcwd(),
        );

        $notStarted = new ProcessResult(
            exitCode: self::FAILED_TO_START,
            output: "Could not start: {$command}",
        );

        return match (is_resource($process)) {
            true => $this->drain($process, $pipes),
            false => $notStarted,
        };
    }

    /**
     * Run a command on this process's own stdin, stdout and stderr, and answer
     * its exit code. Where those are a terminal, the command gets the terminal
     * and can prompt. Nothing is captured: the output goes straight to the user.
     */
    public function passthru(string $command, ?string $workingDirectory = null): int
    {
        $process = proc_open(
            $command,
            [self::INPUT => STDIN, self::OUTPUT => STDOUT, self::ERRORS => STDERR],
            $pipes,
            $workingDirectory ?? getcwd(),
        );

        return match (is_resource($process)) {
            true => proc_close($process),
            false => self::FAILED_TO_START,
        };
    }

    /**
     * Close the child's stdin, read its output to the end, and wait for it.
     *
     * @param  resource  $process
     * @param  array<int, resource>  $pipes
     */
    private function drain(mixed $process, array $pipes): ProcessResult
    {
        [self::INPUT => $input, self::OUTPUT => $output] = $pipes;

        fclose($input);
        $captured = (string) stream_get_contents($output);
        fclose($output);

        return new ProcessResult(exitCode: proc_close($process), output: $captured);
    }
}
