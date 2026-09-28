<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Plugin\LegacyCleanup;
use MikeBronner\DevelopmentSettings\Support\LegacyFingerprint;

beforeEach(function (): void {
    $this->project = makeTempDir();
    $this->package = "{$this->project}/vendor/mike-bronner/laravel-development-settings";
    seedFiles($this->package, ['.ai/guidelines/01.md' => 'shipped']);
});

afterEach(function (): void {
    removeTempDir($this->project);
});

it('removes the stale links and the fingerprint, and lists each', function (): void {
    symlink("{$this->package}/.ai", "{$this->project}/.ai");
    symlink("{$this->project}/vendor/mikebronner/development-settings/x", "{$this->project}/old");
    file_put_contents("{$this->project}/" . LegacyFingerprint::FILE, md5('sources'));

    $lines = (new LegacyCleanup($this->project, $this->package))->clean(['.ai', 'old']);

    expect($lines)->toBe([
        ['unlinked', '.ai'],
        ['unlinked', 'old'],
        ['stale_fingerprint', LegacyFingerprint::FILE],
    ]);
    $removed = "{$this->project}/{.ai,old," . LegacyFingerprint::FILE . '}';

    expect(glob($removed, GLOB_BRACE))->toBe([]);
});

it('removes and lists nothing where nothing is stale', function (): void {
    mkdir("{$this->project}/.ai");

    $lines = (new LegacyCleanup($this->project, $this->package))->clean(['.ai']);

    expect([$lines, is_dir("{$this->project}/.ai")])->toBe([[], true]);
});
