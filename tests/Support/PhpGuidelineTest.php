<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\PhpGuideline;

const PHP_CORE_FIXTURES = REPOSITORY_ROOT . '/tests/Fixtures/php-core';

beforeEach(function (): void {
    $this->package = makeTempDir();
    $this->output = fopen('php://memory', 'w+b');
    $this->errors = fopen('php://memory', 'w+b');
    $this->guideline = new PhpGuideline($this->package, $this->output, $this->errors);
    $this->streams = fn (): array => [
        (string) stream_get_contents($this->output, offset: 0),
        (string) stream_get_contents($this->errors, offset: 0),
    ];
    $this->boost = (string) file_get_contents(PHP_CORE_FIXTURES . '/boost.md');
    $this->replaced = (string) file_get_contents(PHP_CORE_FIXTURES . '/replaced.md');
    $this->changed = fn (string $text): string => str_replace($text, 'Other', $this->boost);
    $this->target = "{$this->package}/" . PhpGuideline::TARGET;
});

afterEach(function (): void {
    removeTempDir($this->package);
});

it('swaps the PHPDoc lines for the no-docblocks rule and keeps the rest', function (): void {
    expect($this->guideline->render($this->boost))->toBe($this->replaced);
});

it('refuses Boost\'s php/core when either PHPDoc line has changed', function (string $text): void {
    expect(fn (): string => $this->guideline->render(($this->changed)($text)))
        ->toThrow(RuntimeException::class, 'no longer holds the line');
})->with([
    'the comments line' => ['Only add inline comments'],
    'the array shape line' => ['Use array shape type definitions'],
]);

it('refuses Boost\'s php/core when another line still mentions PHPDoc', function (): void {
    $extended = "{$this->boost}Keep PHPDoc tags sorted.\n";

    expect(fn (): string => $this->guideline->render($extended))
        ->toThrow(RuntimeException::class, 'still mentions PHPDoc');
});

it('renders the installed Boost php/core without a PHPDoc line', function (): void {
    $installed = (string) file_get_contents(REPOSITORY_ROOT . '/' . PhpGuideline::SOURCE);

    $rendered = $this->guideline
        ->render($installed);

    expect($rendered)->not
        ->toContain('PHPDoc');
    expect($rendered)->toContain(PhpGuideline::NO_DOCBLOCKS_RULE, '@if(', '$assist->enums()');
});

it('writes the replacement from the Boost source, and says so', function (): void {
    seedFiles($this->package, [PhpGuideline::SOURCE => $this->boost]);

    expect($this->guideline->generate())->toBe(0);
    expect(file_get_contents($this->target))->toBe($this->replaced);
    expect(($this->streams)()[0])->toContain(PhpGuideline::TARGET . ' generated');
});

it('fails, writing nothing, when Boost\'s php/core has changed', function (
    string $method,
    string $text,
): void {
    seedFiles($this->package, [PhpGuideline::SOURCE => ($this->changed)($text)]);

    expect($this->guideline->{$method}())->toBe(1);
    expect(file_exists($this->target))->toBeFalse();
    expect(($this->streams)()[1])
        ->toContain(PhpGuideline::TARGET . ' was not generated', 'no longer holds the line');
})->with(['generate', 'check'])
    ->with(['Only add inline comments', 'Use array shape']);

it('leaves the committed replacement alone when Boost\'s php/core has changed', function (): void {
    seedFiles($this->package, [
        PhpGuideline::SOURCE => ($this->changed)('Use array shape'),
        PhpGuideline::TARGET => 'committed',
    ]);

    expect($this->guideline->generate())->toBe(1);
    expect(file_get_contents($this->target))->toBe('committed');
});

it('fails when Boost is not installed', function (string $method): void {
    expect($this->guideline->{$method}())->toBe(1);
    expect(($this->streams)()[1])->toContain('Could not read', PhpGuideline::SOURCE);
    expect(file_exists($this->target))->toBeFalse();
})->with(['generate', 'check']);

it('checks the committed replacement against Boost\'s php/core', function (
    string $committed,
    int $status,
    string $says,
): void {
    seedFiles($this->package, [
        PhpGuideline::SOURCE => $this->boost,
        PhpGuideline::TARGET => (string) file_get_contents(PHP_CORE_FIXTURES . "/{$committed}"),
    ]);

    expect($this->guideline->check())->toBe($status);
    expect(implode('', ($this->streams)()))->toContain(PhpGuideline::TARGET . $says);
})->with([
    'current' => ['replaced.md', 0, " is up to date with Laravel Boost's php/core."],
    'stale' => ['boost.md', 1, " is out of date with Laravel Boost's php/core."],
]);

it('fails the check when no replacement is committed', function (): void {
    seedFiles($this->package, [PhpGuideline::SOURCE => $this->boost]);

    expect($this->guideline->check())->toBe(1);
    expect(($this->streams)()[1])
        ->toContain(PhpGuideline::TARGET . ' is out of date', 'composer dev-settings:guideline');
});

it('ships the replacement the installed Boost produces', function (): void {
    $shipped = new PhpGuideline(REPOSITORY_ROOT, $this->output, $this->errors);

    expect($shipped->check())->toBe(0);
});
