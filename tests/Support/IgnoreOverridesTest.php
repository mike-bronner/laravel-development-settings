<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\IgnoreOverrides;

const SHIPPED_RULES = <<<GITIGNORE
    /vendor
    # Laravel's placeholders stay tracked.
    /storage/logs/*
    !/storage/logs/.gitignore

    GITIGNORE;

const FIRST_PROJECT_LINE = 6;

beforeEach(function (): void {
    $this->find = fn (string $project): array => (new IgnoreOverrides)
        ->find(marked(SHIPPED_RULES, "\n{$project}"), SHIPPED_RULES);
    $this->ignores = fn (string $label): string => sprintf(IgnoreOverrides::IGNORES, $label);
});

it('finds no override in a project part that overrides nothing', function (string $project): void {
    expect(($this->find)($project))->toBe([]);
})->with([
    'nothing below the marker' => [''],
    'rules of its own, and a comment naming a shipped one' => [<<<GITIGNORE
        /deprecations.log
        phpunit.xml
        # /vendor

        GITIGNORE],
    'a negation of a tracked path' => ["!AGENTS.md\n"],
    'an ignore undone by a later negation' => ["AGENTS.md\n!AGENTS.md\n"],
    'a near miss of a tracked path' => ["AGENTS.md.bak\n/docs/CLAUDE.md\n.aider\n"],
    'a directory rule over a file' => ["CLAUDE.md/\n"],
    'an escaped negation, alone' => ["\\!AGENTS.md\n"],
    'an escaped trailing space' => ["AGENTS.md\\ \n"],
    'a directory ignored, then re-included' => [".ai/\n!.ai/\n"],
]);

it('reads a line it cannot compile as matching nothing', function (string $line): void {
    $overrides = throwingOnWarnings(fn (): array => ($this->find)("{$line}\nAGENTS.md\n"));

    expect(array_values($overrides))->toBe([['AGENTS.md', [($this->ignores)('AGENTS.md')]]]);
})->with(['[#]x', '[z-a]', '[]]', 'CLAUDE.m[z-a]', '[!]', '[']);

it('reports a path re-included below a directory the project ignores', function (
    string $project,
    string $line,
    string $label,
): void {
    $overrides = ($this->find)($project);

    expect(data_get($overrides, FIRST_PROJECT_LINE . '.0'))->toBe($line);
    expect(data_get($overrides, FIRST_PROJECT_LINE . '.1'))->toContain(($this->ignores)($label));
})->with([
    'a placeholder under storage' => [
        "/storage/*\n!/storage/logs/.gitignore\n",
        '/storage/*',
        'storage/logs/.gitignore',
    ],
    'guidelines under .ai' => [".ai/\n!.ai/guidelines/\n", '.ai/', '.ai/'],
]);

it('reads escapes and trailing spaces as git does', function (string $project, string $line): void {
    $overrides = ($this->find)($project);

    expect(array_values($overrides))->toBe([[$line, [($this->ignores)('AGENTS.md')]]]);
})->with([
    'an escaped negation is no negation' => ["AGENTS.md\n\\!AGENTS.md\n", 'AGENTS.md'],
    'unescaped trailing spaces are dropped' => ["AGENTS.md   \n", 'AGENTS.md'],
]);

it('names a line that repeats a shipped rule, by its line in the file', function (): void {
    expect(($this->find)("/deprecations.log\n/vendor\n"))
        ->toBe([FIRST_PROJECT_LINE + 1 => ['/vendor', [IgnoreOverrides::REPEATS]]]);
});

it('names every line that ignores what the shipped rules leave tracked', function (
    string $line,
    string $label,
): void {
    expect(($this->find)("{$line}\n"))
        ->toBe([FIRST_PROJECT_LINE => [$line, [($this->ignores)($label)]]]);
})->with([
    'the .ai directory' => ['.ai', '.ai/'],
    'the .ai directory, as a directory' => ['/.ai/', '.ai/'],
    'the .ai contents' => ['.ai/*', '.ai/'],
    'AGENTS.md' => ['AGENTS.md', 'AGENTS.md'],
    'CLAUDE.md, anchored' => ['/CLAUDE.md', 'CLAUDE.md'],
    'CLAUDE.md, by a wildcard' => ['CLAUDE.m?', 'CLAUDE.md'],
    'CLAUDE.md, by a class' => ['CLAUDE.[mM]d', 'CLAUDE.md'],
    'the bootstrap cache directory' => ['/bootstrap/cache', 'bootstrap/cache/.gitignore'],
    'placeholders by name' => ['**/storage/logs/.gitignore', 'storage/logs/.gitignore'],
]);

it('names every path a line ignores, and every line that ignores a path', function (): void {
    $ignores = $this->ignores;

    $overrides = ($this->find)(".ai\n/storage/framework\n.ai/\n");

    expect(array_values($overrides))->toBe([
        ['.ai', [$ignores('.ai/')]],
        ['/storage/framework', [
            $ignores('storage/framework/.gitignore'),
            $ignores('storage/framework/views/.gitignore'),
        ]],
        ['.ai/', [$ignores('.ai/')]],
    ]);
    expect(array_key_first($overrides))->toBe(FIRST_PROJECT_LINE);
});

it('gives both reasons to a repeated rule that ignores a tracked path', function (): void {
    $reasons = [IgnoreOverrides::REPEATS, ($this->ignores)('storage/logs/.gitignore')];

    expect(($this->find)("/storage/logs/*\n"))
        ->toBe([FIRST_PROJECT_LINE => ['/storage/logs/*', $reasons]]);
});

it('reads no project part without exactly one marker', function (string $contents): void {
    expect((new IgnoreOverrides)->find($contents, SHIPPED_RULES))->toBe([]);
})->with([
    'no marker' => ["/vendor\n.ai\n"],
    'two markers' => [marked("/vendor\n") . marked('', "\n.ai\n")],
]);
