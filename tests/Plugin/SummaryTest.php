<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Plugin\Summary;
use MikeBronner\DevelopmentSettings\Plugin\Tally;

const BOX_WIDTH = 80;

const RULE = 78;

const TITLE_GAP = 58;

const LONG_PATH_LENGTH = 90;

const CUT_TO = 67;

const TWO_NEW = 2;

beforeEach(function (): void {
    $this->visible = fn (string $line): string => (string) preg_replace('/<[^>]+>/', '', $line);
});

it('opens the box with the title, as wide as the box', function (): void {
    $lines = collect((new Summary())->header())
        ->map($this->visible)
        ->all();

    expect($lines)->toBe([
        '',
        '┌' . str_repeat('─', RULE) . '┐',
        '│  Developer Settings' . str_repeat(' ', TITLE_GAP) . '│',
        '├' . str_repeat('─', RULE) . '┤',
    ]);
});

it('marks a line with an icon and note', function (string $type, string $icon, string $note): void {
    $line = ($this->visible)((new Summary())->line($type, '.gitignore'));

    expect($line)->toStartWith("│  {$icon}  .gitignore{$note}")
        ->and($line)
        ->toEndWith('  │');
})->with([
    'created' => ['created', '+', ' '],
    'updated' => ['updated', '↻', ' '],
    'modified' => ['modified', '⚠', ' (locally modified)'],
    'unmarked' => ['unmarked', '⚠', ' (locally modified, no sync marker)'],
    'refused' => ['refused', '⚠', ' (sync marker appears twice, not touched)'],
    'failed' => ['failed', '✗', ' (write failed)'],
    'removed' => ['removed', '-', ' '],
    'unlinked' => ['unlinked', '-', ' (stale symlink into vendor)'],
    'registered' => ['registered', '+', ' (registered with Boost)'],
]);

it('pads a line to the width of the box', function (): void {
    $line = ($this->visible)((new Summary())->line('created', 'pint.json'));

    expect(mb_strlen($line))->toBe(BOX_WIDTH);
});

it('cuts a path too long for the box, and marks the cut', function (): void {
    $line = ($this->visible)((new Summary())->line('created', str_repeat('a', LONG_PATH_LENGTH)));

    expect($line)->toContain(str_repeat('a', CUT_TO) . '... ')
        ->not
        ->toContain(str_repeat('a', CUT_TO + 1))
        ->and(mb_strlen($line))
        ->toBe(BOX_WIDTH);
});

it('closes the box with every count, greyed out at zero', function (): void {
    $tally = new Tally();
    $tally->add(Tally::NEW, TWO_NEW);
    [, $summary] = (new Summary())->footer($tally);

    expect($summary)->toContain('<fg=bright-green;bg=green> 2 new </>', '<fg=gray>0 unchanged</>')
        ->and(($this->visible)($summary))
        ->toContain(' 2 new  · 0 updated · 0 unchanged · 0 skipped · 0 removed');
});
