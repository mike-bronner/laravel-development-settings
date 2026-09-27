<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\LegacyFingerprint;

beforeEach(function (): void {
    $this->project = makeTempDir();
    $this->path = "{$this->project}/" . LegacyFingerprint::FILE;
});

afterEach(function (): void {
    removeTempDir($this->project);
});

it('removes a fingerprint file the old runner wrote', function (): void {
    file_put_contents($this->path, md5('sources') . "\n");
    $fingerprint = new LegacyFingerprint();

    expect($fingerprint->isStale($this->project))->toBeTrue();

    $fingerprint->remove($this->project);

    expect(file_exists($this->path))->toBeFalse();
});

it('reports nothing, and removes nothing, when there is no fingerprint file', function (): void {
    $fingerprint = new LegacyFingerprint();
    $fingerprint->remove($this->project);

    expect($fingerprint->isStale($this->project))->toBeFalse();
});

it('keeps anything but a bare fingerprint in a regular file', function (string $shape): void {
    match ($shape) {
        'a file with notes' => file_put_contents($this->path, md5('sources') . "\nproject notes\n"),
        'a directory' => mkdir($this->path),
        'a link to a fingerprint' => file_put_contents("{$this->project}/elsewhere", md5('sources'))
            && symlink('elsewhere', $this->path),
    };
    $fingerprint = new LegacyFingerprint();
    $fingerprint->remove($this->project);

    expect([$fingerprint->isStale($this->project), file_exists($this->path)])->toBe([false, true]);
})->with(['a file with notes', 'a directory', 'a link to a fingerprint']);
