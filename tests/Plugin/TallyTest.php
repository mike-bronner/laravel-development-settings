<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Plugin\Tally;

const REMOVED_AT_ONCE = 3;

const FOUR_REMOVED = 4;

it('starts every count at zero', function (string $kind): void {
    expect((new Tally)->count($kind))->toBe(0);
})->with([Tally::NEW, Tally::UPDATED, Tally::UNCHANGED, Tally::SKIPPED, Tally::REMOVED]);

it('adds one by default, or as many as it is given, to one count only', function (): void {
    $tally = new Tally;

    $tally->add(Tally::NEW);
    $tally->add(Tally::REMOVED, REMOVED_AT_ONCE);
    $tally->add(Tally::REMOVED);

    expect($tally->count(Tally::NEW))->toBe(1);
    expect($tally->count(Tally::REMOVED))->toBe(FOUR_REMOVED);
    expect($tally->count(Tally::SKIPPED))->toBe(0);
});
