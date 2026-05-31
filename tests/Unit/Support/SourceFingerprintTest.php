<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\SourceFingerprint;

function fpConfig(): array
{
    return ['paths' => ['symlinks' => ['.ai' => '.ai']]];
}

it('is stable for identical content', function (): void {
    $pkg = makeTempDir();
    mkdir($pkg . '/.ai/guidelines', 0755, true);
    file_put_contents($pkg . '/.ai/guidelines/a.md', 'same');

    $fp = new SourceFingerprint;

    expect($fp->forSymlinks($pkg, fpConfig()))->toBe($fp->forSymlinks($pkg, fpConfig()));

    removeTempDir($pkg);
});

it('changes when a source file is edited', function (): void {
    $pkg = makeTempDir();
    mkdir($pkg . '/.ai/guidelines', 0755, true);
    file_put_contents($pkg . '/.ai/guidelines/a.md', 'v1');

    $fp = new SourceFingerprint;
    $before = $fp->forSymlinks($pkg, fpConfig());

    file_put_contents($pkg . '/.ai/guidelines/a.md', 'v2');
    $after = $fp->forSymlinks($pkg, fpConfig());

    expect($after)->not->toBe($before);

    removeTempDir($pkg);
});

it('changes when a source file is added or removed', function (): void {
    $pkg = makeTempDir();
    mkdir($pkg . '/.ai/guidelines', 0755, true);
    file_put_contents($pkg . '/.ai/guidelines/a.md', 'a');

    $fp = new SourceFingerprint;
    $one = $fp->forSymlinks($pkg, fpConfig());

    file_put_contents($pkg . '/.ai/guidelines/b.md', 'b');
    $two = $fp->forSymlinks($pkg, fpConfig());

    expect($two)->not->toBe($one);

    removeTempDir($pkg);
});
