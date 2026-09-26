<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\ProcessResult;

it('counts only a zero exit as success', function (int $exitCode, bool $failed): void {
    expect((new ProcessResult(exitCode: $exitCode, output: ''))->failed())->toBe($failed);
})->with([
    'zero' => [0, false],
    'one' => [1, true],
    'negative, as proc_close reports a signal' => [-1, true],
]);

it('answers the last lines of output, without blank lines or trailing whitespace', function (): void {
    $result = new ProcessResult(exitCode: 1, output: "first\r\n\n  second  \n   \nthird\n\n");

    expect($result->tail())->toBe(['first', '  second', 'third'])
        ->and($result->tail(2))->toBe(['  second', 'third']);
});

it('answers no lines for empty output', function (): void {
    expect((new ProcessResult(exitCode: 1, output: ''))->tail())->toBe([]);
});

it('keeps twenty lines by default', function (): void {
    $result = new ProcessResult(exitCode: 1, output: implode("\n", range(1, 25)));

    expect($result->tail())->toBe(array_map(strval(...), range(6, 25)));
});
