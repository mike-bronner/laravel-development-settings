<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\IgnorePattern;

const FUZZ_SEED = 20_261_007;

const FUZZ_RULES = 5_000;

const FUZZ_LONGEST_RULE = 8;

it('matches a path as git does', function (string $rule, string $path): void {
    expect((new IgnorePattern($rule))->matches($path))->toBeTrue();
})->with([
    'a name, at any depth' => ['CLAUDE.md', 'docs/CLAUDE.md'],
    'a directory rule, on a directory' => ['.ai/', '.ai/'],
    'a star inside a segment' => ['/storage/*', 'storage/logs/'],
    'a double star between, zero directories' => ['a/**/b', 'a/b'],
    'a double star between, several directories' => ['a/**/b', 'a/x/y/b'],
    'a double star at the end' => ['/storage/**', 'storage/a/b'],
    'a double star at the start' => ['**/logs', 'storage/logs/'],
    'a question mark' => ['CLAUDE.m?', 'CLAUDE.md'],
    'a class' => ['CLAUDE.[mM]d', 'CLAUDE.Md'],
    'a negated class, by bang' => ['[!a]x', 'bx'],
    'a negated class, by caret' => ['[^a]x', 'bx'],
    'a range' => ['[a-c]x', 'bx'],
    'a hyphen last in a class' => ['[a-]x', '-x'],
    'a closing bracket first in a class' => ['[]]', ']'],
    'a hash in a class' => ['[#]x', '#x'],
    'an escaped hash' => ['\#x', '#x'],
    'an escaped bang' => ['\!x', '!x'],
    'an escaped character in a class' => ['[\]]x', ']x'],
    'an unclosed bracket, as itself' => ['[', '['],
    'a negation, by its pattern' => ['!AGENTS.md', 'AGENTS.md'],
]);

it('does not match a path git would not match', function (string $rule, string $path): void {
    expect((new IgnorePattern($rule))->matches($path))->toBeFalse();
})->with([
    'an anchored name, below the root' => ['/CLAUDE.md', 'docs/CLAUDE.md'],
    'a directory rule, on a file' => ['CLAUDE.md/', 'CLAUDE.md'],
    'a star across a slash' => ['/storage/*', 'storage/logs/.gitignore'],
    'a negated class, on its character' => ['[!a]x', 'ax'],
    'a range, outside it' => ['[a-c]x', 'dx'],
    'an escaped star, as a wildcard' => ['\*', 'x'],
]);

it('tells a negation from an escaped bang', function (string $rule): void {
    expect([
        (new IgnorePattern("!{$rule}"))->isNegation(),
        (new IgnorePattern("\\!{$rule}"))->isNegation(),
        (new IgnorePattern($rule))->isNegation(),
    ])->toBe([true, false, false]);
})->with(['AGENTS.md', '/.ai/']);

it('matches nothing, and raises nothing, for a rule it cannot compile', function (
    string $rule,
    string $path,
): void {
    $matches = throwingOnWarnings(fn (): bool => (new IgnorePattern($rule))->matches($path));

    expect($matches)->toBeFalse();
})->with([
    'a range out of order' => ['[z-a]', 'a'],
    'an empty negated class' => ['[!]', '!'],
    'only an anchor' => ['/', 'x'],
]);

/*
 * Every character an ignore rule can make special in a regular expression,
 * in random rules from a fixed seed: none may raise a PCRE warning.
 */
it('compiles every rule without a warning', function (): void {
    $characters = collect(str_split('#[]!^-\\*?/a.$()|{}+ '));
    mt_srand(seed: FUZZ_SEED);
    $rules = collect(range(1, FUZZ_RULES))
        ->map(fn (): string => collect(range(1, mt_rand(1, FUZZ_LONGEST_RULE)))
            ->map(fn (): string => $characters->random())
            ->implode(''));

    $checked = throwingOnWarnings(fn (): array => $rules
        ->map(fn (string $rule): bool => (new IgnorePattern($rule))->matches('a/b'))
        ->all());

    expect($checked)->toHaveCount(FUZZ_RULES);
});

it('drops trailing spaces unless a backslash escapes them', function (
    string $rule,
    string $read,
    string $path,
): void {
    $pattern = new IgnorePattern($rule);

    expect([$pattern->rule(), $pattern->matches($path)])->toBe([$read, true]);
})->with([
    'unescaped spaces' => ['AGENTS.md   ', 'AGENTS.md', 'AGENTS.md'],
    'an escaped space' => ['x\ ', 'x\ ', 'x '],
    'an escaped space, then unescaped ones' => ['x\   ', 'x\ ', 'x '],
    'a carriage return' => ["AGENTS.md\r", 'AGENTS.md', 'AGENTS.md'],
]);
