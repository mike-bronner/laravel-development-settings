<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

// Extra environment variables reach the command only. They are never set on
// the Composer process itself, so nothing that runs after it inherits them.
final class SystemProcess implements Process
{
    public function run(string $command, ?string $workingDirectory = null, array $environment = []): int
    {
        return $this->capture($command, $workingDirectory, $environment)->exitCode;
    }

    public function capture(string $command, ?string $workingDirectory = null, array $environment = []): ProcessResult
    {
        // Stderr is redirected into the stdout pipe, so the output keeps the
        // order it was written in, and there is only one pipe to drain. Two
        // pipes read one after the other deadlock once the child fills the
        // one not being read.
        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['redirect', 1],
            ],
            $pipes,
            $workingDirectory ?? getcwd(),
            $environment === [] ? null : [...getenv(), ...$environment],
        );

        if (! is_resource($process)) {
            return new ProcessResult(exitCode: 1, output: "Could not start: {$command}");
        }

        fclose($pipes[0]);
        $output = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        return new ProcessResult(exitCode: proc_close($process), output: $output);
    }
}
