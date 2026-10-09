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

    // phpcs:disable CleanCode.Pattern.AvoidDuplicateCodeBlocks.Found
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
    // phpcs:enable CleanCode.Pattern.AvoidDuplicateCodeBlocks.Found

    private function drain(mixed $process, array $pipes): ProcessResult
    {
        [self::INPUT => $input, self::OUTPUT => $output] = $pipes;

        fclose($input);
        $captured = (string) stream_get_contents($output);
        fclose($output);

        return new ProcessResult(exitCode: proc_close($process), output: $captured);
    }
}
