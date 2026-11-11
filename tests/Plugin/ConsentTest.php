<?php

declare(strict_types=1);

use Composer\IO\BufferIO;
use Laravel\Prompts\Key;
use MikeBronner\DevelopmentSettings\Plugin\Consent;

beforeEach(function (): void {
    $this->output = new BufferIO;
    $this->interactive = new BufferIO;
    $this->interactive
        ->setUserInputs([]);
});

it('asks nothing, and selects nothing, about no file', function (string $question): void {
    $consent = new Consent($this->interactive);

    expect($consent->{$question}([]))->toBe([]);
    expect($this->interactive->getOutput())->toBe('');
})->with(['overwrite', 'convert', 'orphansToDelete']);

it('selects nothing, silently, to overwrite in a run that cannot ask', function (): void {
    expect((new Consent($this->output))->overwrite(['pint.json']))->toBe([]);
    expect($this->output->getOutput())->toBe('');
});

it('selects nothing in a run that cannot ask, and says what it kept', function (
    string $question,
    string $message,
): void {
    expect((new Consent($this->output))->{$question}(['a', 'b']))->toBe([]);
    expect($this->output->getOutput())->toContain($message);
})->with([
    'convert' => ['convert', '2 locally-modified file(s) have no sync marker and were not'],
    'delete' => ['orphansToDelete', '2 locally-modified file(s) removed upstream were kept.'],
]);

it('selects what the developer ticks, and nothing by default', function (
    string $question,
    array $keys,
    array $selected,
): void {
    $consent = new Consent($this->interactive);

    $answer = withKeyPresses($keys, fn (): array => $consent->{$question}(['a', 'b']));

    expect($answer)->toBe($selected);
})->with([
    'overwrite the first' => ['overwrite', [Key::SPACE, Key::ENTER], ['a']],
    'convert none' => ['convert', [Key::ENTER], []],
    'delete the second' => ['orphansToDelete', [Key::DOWN, Key::SPACE, Key::ENTER], ['b']],
]);

it('says why each file holding the marker twice was not touched', function (): void {
    (new Consent($this->output))->explainRefused(['.gitignore', '.gitattributes']);

    expect($this->output->getOutput())->toContain(
            '.gitignore holds the sync marker more than once, so it was not touched.',
            '.gitattributes holds the sync marker more than once, so it was not touched.',
        );
});

it('keeps the project template in a run that cannot ask, and names it', function (): void {
    $consent = new Consent($this->output);

    expect($consent->replaceTemplate('shipped.md', ['a.md', 'b/c.md'], []))->toBeFalse();
    expect($this->output->getOutput())->toContain(
            'shipped.md was not written, because the project has its own pull request template:'
                . ' a.md, b/c.md. Run composer interactively to replace it with the shipped one.',
        );
});

it('keeps the project template when the developer takes the default', function (): void {
    $consent = new Consent($this->interactive);

    $answer = withKeyPresses(
            [Key::ENTER],
            fn (): bool => $consent->replaceTemplate('s.md', ['a.md'], []),
        );

    expect($answer)->toBeFalse();
    expect($this->interactive->getOutput())->toBe('');
});

it('replaces the project pull request template when the developer picks it', function (): void {
    $consent = new Consent($this->interactive);

    $answer = withKeyPresses(
            [Key::DOWN, Key::ENTER],
            fn (): bool => $consent->replaceTemplate('s.md', ['a.md'], []),
        );

    expect($answer)->toBeTrue();
});

it('neither asks nor replaces when a folder could not be searched', function (): void {
    $consent = new Consent($this->interactive);
    $unreadable = ['docs' => 'Could not open /p/docs: Permission denied'];

    expect($consent->replaceTemplate('s.md', ['a.md'], $unreadable))->toBeFalse();
    expect($this->interactive->getOutput())->toContain(
            's.md was not written, because a folder could not be searched for the project\'s'
                . ' own pull request template. Could not open /p/docs: Permission denied',
        );
});
