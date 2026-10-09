<?php

declare(strict_types=1);

use Laravel\Prompts\Key;

const AGREE = [Key::SPACE, Key::ENTER];

const DECLINE = [Key::ENTER];

afterEach(function (): void {
    removeTempDir($this->project);
});

it('syncs above the marker, unasked', function (string $file, string $synced, string $count): void {
    $this->project = gitignoreConsumer($file);

    $output = publishIn($this->project);

    expect(file_get_contents("{$this->project}/.gitignore"))->toBe($synced);
    expect($output)->toContain($count);
    expect($output)->not
        ->toContain('locally modified');
})->with([
    'a known version: updated, project lines kept' => [
        marked(GITIGNORE_V1, "\n!AGENTS.md\n/deprecations.log\n"),
        marked(GITIGNORE_V2, "\n!AGENTS.md\n/deprecations.log\n"),
        '1 updated',
    ],
    'the current version: left alone' => [
        marked(GITIGNORE_V2, "\nphpunit.xml\n"),
        marked(GITIGNORE_V2, "\nphpunit.xml\n"),
        '1 unchanged',
    ],
    'an unmarked shipped version: converted' => [GITIGNORE_V1, marked(GITIGNORE_V2), '1 updated'],
]);

it('only warns about an edited, unmarked .gitignore in a non-interactive run', function (): void {
    $this->project = gitignoreConsumer(GITIGNORE_V1 . "!AGENTS.md\n");

    $output = publishIn($this->project);

    expect(file_get_contents("{$this->project}/.gitignore"))->toBe(GITIGNORE_V1 . "!AGENTS.md\n");
    expect($output)->toContain(
            '.gitignore (locally modified, no sync marker)',
            '1 locally-modified file(s) have no sync marker and were not updated',
            '1 skipped',
        );
});

it('changes an edited .gitignore only on consent', function (
    string $local,
    array $keys,
    string $synced,
    string $count,
): void {
    $this->project = gitignoreConsumer($local);

    $output = withKeyPresses($keys, fn (): string => publishIn($this->project, INTERACTIVE));

    expect(file_get_contents("{$this->project}/.gitignore"))->toBe($synced);
    expect($output)->toContain($count);
})->with([
    'unmarked, converted: the whole file below the marker' => [
        GITIGNORE_V1 . "!AGENTS.md\n",
        AGREE,
        marked(GITIGNORE_V2, "\n" . GITIGNORE_V1 . "!AGENTS.md\n"),
        '1 updated',
    ],
    'unmarked, kept, which is the default' => [
        GITIGNORE_V1 . "!AGENTS.md\n",
        DECLINE,
        GITIGNORE_V1 . "!AGENTS.md\n",
        '1 skipped',
    ],
    'edited above the marker, overwritten: project lines kept' => [
        marked(GITIGNORE_V1 . "/edited-above\n", "\n!AGENTS.md\nno trailing newline"),
        AGREE,
        marked(GITIGNORE_V2, "\n!AGENTS.md\nno trailing newline"),
        '1 updated',
    ],
    'edited above the marker, kept' => [
        marked(GITIGNORE_V1 . "/edited-above\n", "\n!AGENTS.md\n"),
        DECLINE,
        marked(GITIGNORE_V1 . "/edited-above\n", "\n!AGENTS.md\n"),
        '1 skipped',
    ],
]);

it('reports a known-version .gitignore it cannot write as failed, not updated', function (): void {
    $this->project = gitignoreConsumer(GITIGNORE_V1);
    chmod("{$this->project}/.gitignore", MODE_READ_ONLY);

    $output = publishIn($this->project);
    chmod("{$this->project}/.gitignore", MODE_WRITABLE);

    expect(file_get_contents("{$this->project}/.gitignore"))->toBe(GITIGNORE_V1);
    expect($output)->toContain(
            '.gitignore (write failed)',
            '.gitignore was not updated. Could not write ',
            'Permission denied',
            '0 updated',
            '1 skipped',
        );
    expect($output)->not
        ->toContain('↻');
});

it('counts an agreed overwrite it cannot write as skipped, and says why', function (): void {
    $local = marked(GITIGNORE_V1 . "/edited-above\n", "\n!AGENTS.md\n");
    $this->project = gitignoreConsumer($local);
    chmod("{$this->project}/.gitignore", MODE_READ_ONLY);

    $output = withKeyPresses(AGREE, fn (): string => publishIn($this->project, INTERACTIVE));
    chmod("{$this->project}/.gitignore", MODE_WRITABLE);

    expect(file_get_contents("{$this->project}/.gitignore"))->toBe($local);
    expect($output)
        ->toContain('.gitignore was not updated. Could not write', '0 updated', '1 skipped');
});

it('does not touch a .gitignore holding the sync marker twice, and says why', function (): void {
    $local = marked(GITIGNORE_V1, "\n!AGENTS.md\n") . marked('');
    $this->project = gitignoreConsumer($local);

    $output = withKeyPresses([], fn (): string => publishIn($this->project, INTERACTIVE));

    expect(file_get_contents("{$this->project}/.gitignore"))->toBe($local);
    expect($output)->toContain(
            '.gitignore (sync marker appears twice, not touched)',
            '.gitignore holds the sync marker more than once, so it was not touched',
            '1 skipped',
        );
});

it('lists the project lines that override the shipped rules, and keeps them', function (): void {
    $local = marked(GITIGNORE_V2, "\n.ai\nAGENTS.md\n/vendor\n!AGENTS.md\n/deprecations.log\n");
    $this->project = gitignoreConsumer($local);

    $output = publishIn($this->project);

    expect(file_get_contents("{$this->project}/.gitignore"))->toBe($local);
    expect($output)->toContain(
            'line 4: .ai (ignores .ai/, which the shipped rules leave tracked)',
            'line 6: /vendor (repeats a shipped rule)',
        );
    expect($output)->not
        ->toContain('line 5:', 'line 7:', 'line 8:');
});
