<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

/**
 * proc_open-backed Process implementation. Runs the command, drains stdout and
 * stderr (discarding them), and returns the exit code.
 */
final class SystemProcess implements Process
{
    public function run(string $command, ?string $workingDirectory = null): int
    {
        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $workingDirectory ?? getcwd(),
        );

        if (! is_resource($process)) {
            return 1;
        }

        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($process);
    }
}
