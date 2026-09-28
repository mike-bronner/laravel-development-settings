<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\ProcessResult;

const DEFAULT_TAIL = 20;

const LONGER_THAN_THE_TAIL = 25;

const TWO_LINES = 2;

it('answers the exit code and the output it was given', function (): void {
    $result = new ProcessResult(exitCode: 0, output: "done\n");

    expect([$result->exitCode(), $result->output(), $result->failed()])->toBe([0, "done\n", false]);
});

it('counts any exit but zero as a failure', function (int $exitCode): void {
    expect((new ProcessResult(exitCode: $exitCode, output: ''))->failed())->toBeTrue();
})->with([
    'one' => [1],
    'negative, as proc_close reports a signal' => [-1],
]);

it('answers the last lines of output, with no blank line or trailing space', function (): void {
    $result = new ProcessResult(exitCode: 1, output: "first\r\n\n  second  \n   \nthird\n\n");

    expect($result->tail())->toBe(['first', '  second', 'third'])
        ->and($result->tail(TWO_LINES))
        ->toBe(['  second', 'third']);
});

it('answers no lines for empty output', function (): void {
    expect((new ProcessResult(exitCode: 1, output: ''))->tail())->toBe([]);
});

it('keeps twenty lines by default', function (): void {
    $lines = range(1, LONGER_THAN_THE_TAIL);
    $result = new ProcessResult(exitCode: 1, output: implode("\n", $lines));

    expect($result->tail())->toBe(collect($lines)
        ->slice(-DEFAULT_TAIL)
        ->map(fn (int $line): string => (string) $line)
        ->values()
        ->all());
});
