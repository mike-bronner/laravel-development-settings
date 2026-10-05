<?php

declare(strict_types=1);

use Composer\IO\BufferIO;
use MikeBronner\DevelopmentSettings\Plugin\Consent;
use MikeBronner\DevelopmentSettings\Plugin\Tally;
use MikeBronner\DevelopmentSettings\Plugin\TrackedFiles;
use MikeBronner\DevelopmentSettings\Support\FileSync;
use MikeBronner\DevelopmentSettings\Support\Manifest;

const KEPT_BY_DEFAULT = 2;

const FIRST_TWO = 2;

beforeEach(function (): void {
    $this->source = makeTempDir();
    $this->project = makeTempDir();
    $this->output = new BufferIO();
    seedFiles($this->source, ['new.md' => 'new', 'known.md' => 'v2', 'edited.md' => 'v2']);
    seedFiles($this->project, [
        'known.md' => 'v1',
        'edited.md' => 'mine',
        'retired.md' => 'shipped',
        'kept.md' => 'mine',
    ]);
    $this->files = new TrackedFiles(
            new FileSync(new Manifest([
                'known.md' => [md5('v1')],
                'edited.md' => [md5('v1')],
                'retired.md' => [md5('shipped')],
                'kept.md' => [md5('shipped')],
            ])),
            $this->project,
            $this->output,
        );
    $this->files
        ->classify(collect(['new.md', 'known.md', 'edited.md'])
            ->mapWithKeys(fn (string $path): array => [$path => "{$this->source}/{$path}"])
            ->all());
});

afterEach(function (): void {
    removeTempDir($this->source);
    removeTempDir($this->project);
});

it('writes the new and known-version files, and lists every file it saw', function (): void {
    $files = $this->files;

    $files->writeUnattended();

    expect(file_get_contents("{$this->project}/new.md"))->toBe('new');
    expect(file_get_contents("{$this->project}/known.md"))->toBe('v2');
    expect($files->summaryLines())->toBe([
        ['created', 'new.md'],
        ['updated', 'known.md'],
        ['modified', 'edited.md'],
        ['removed', 'retired.md'],
        ['orphan_protected', 'kept.md'],
    ]);
});

it('keeps what it was not allowed to change, deletes safe orphans, and counts', function (): void {
    $files = $this->files;
    $tally = new Tally();

    $files->writeUnattended();
    $files->settle(new Consent($this->output), $tally);

    expect(collect([Tally::NEW, Tally::UPDATED, Tally::UNCHANGED, Tally::SKIPPED, Tally::REMOVED])
        ->map(fn (string $kind): int => $tally->count($kind))
        ->all())->toBe([1, 1, 0, KEPT_BY_DEFAULT, 1]);
    expect(file_get_contents("{$this->project}/edited.md"))->toBe('mine');
    expect(glob("{$this->project}/{retired,kept}.md", GLOB_BRACE))
        ->toBe(["{$this->project}/kept.md"]);
});

it('lists and counts a write that fails, and says why', function (): void {
    chmod("{$this->project}/known.md", MODE_READ_ONLY);
    $files = $this->files;
    $tally = new Tally();

    $files->writeUnattended();
    $lines = $files->summaryLines();
    $files->settle(new Consent($this->output), $tally);
    chmod("{$this->project}/known.md", MODE_WRITABLE);

    expect(array_slice($lines, 0, FIRST_TWO))
        ->toBe([['failed', 'known.md'], ['created', 'new.md']]);
    expect([$tally->count(Tally::UPDATED), $tally->count(Tally::SKIPPED)])
        ->toBe([0, KEPT_BY_DEFAULT + 1]);
    expect($this->output->getOutput())->toContain('known.md was not updated. Could not copy');
});
