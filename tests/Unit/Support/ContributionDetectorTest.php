<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\ContributionDetector;
use MikeBronner\DevelopmentSettings\Support\Manifest;

it('detects symlink-source files edited away from any known checksum', function (): void {
    $pkg = makeTempDir();
    mkdir($pkg . '/.ai/guidelines', 0755, true);
    file_put_contents($pkg . '/.ai/guidelines/edited.md', 'locally changed');
    file_put_contents($pkg . '/.ai/guidelines/pristine.md', 'shipped');

    $manifest = new Manifest([
        '.ai/guidelines/edited.md' => [md5('original')],   // local content differs -> modified
        '.ai/guidelines/pristine.md' => [md5('shipped')],  // matches known -> not modified
    ]);

    $modified = (new ContributionDetector)->modified(
        $pkg,
        ['paths' => ['symlinks' => ['.ai' => '.ai']]],
        $manifest,
    );

    expect(array_keys($modified))->toBe(['.ai/guidelines/edited.md']);

    removeTempDir($pkg);
});

it('returns nothing when no symlinks are configured', function (): void {
    $pkg = makeTempDir();

    $modified = (new ContributionDetector)->modified($pkg, ['paths' => []], new Manifest);

    expect($modified)->toBe([]);

    removeTempDir($pkg);
});
