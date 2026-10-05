<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Plugin\ConsoleStyle;

it('wraps text in the style it names', function (): void {
    $expected = <<<TEXT
        <comment>  kept</comment>
        TEXT;

    expect((new ConsoleStyle)->wrap('comment', '  kept'))->toBe($expected);
});

it('escapes a tag inside text from elsewhere, so it is printed', function (): void {
    $text = <<<TEXT
        <error>command</error>
        TEXT;

    $escaped = str_replace(['<', '>'], ['\\<', '\\>'], $text);

    expect((new ConsoleStyle)->escape($text))->toBe($escaped);
});
