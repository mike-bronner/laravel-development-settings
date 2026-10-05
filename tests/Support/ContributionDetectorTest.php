<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\ContributionDetector;
use MikeBronner\DevelopmentSettings\Support\Manifest;

beforeEach(function (): void {
    $this->package = makeTempDir();
});

afterEach(function (): void {
    removeTempDir($this->package);
});

it('detects installed sources edited away from every shipped version', function (): void {
    seedFiles($this->package, [
        'resources/boost/guidelines/edited.md' => 'locally changed',
        'resources/boost/guidelines/pristine.md' => 'shipped',
        'resources/boost/skills/older/SKILL.md' => 'shipped last release',
    ]);
    $sources = new Manifest([
        'resources/boost/guidelines/edited.md' => [md5('original')],
        'resources/boost/guidelines/pristine.md' => [md5('shipped')],
        'resources/boost/skills/older/SKILL.md' => [md5('shipped last release'), md5('now')],
    ]);

    $modified = (new ContributionDetector)
        ->modified($this->package, ['resources/boost'], $sources);
    $edited = 'resources/boost/guidelines/edited.md';

    expect($modified)->toBe([$edited => "{$this->package}/{$edited}"]);
});

it('detects a source the developer added, which no release ever shipped', function (): void {
    seedFiles($this->package, ['resources/boost/skills/new-skill/SKILL.md' => 'new']);

    $modified = (new ContributionDetector)
        ->modified($this->package, ['resources/boost'], new Manifest);

    expect(array_keys($modified))->toBe(['resources/boost/skills/new-skill/SKILL.md']);
});

it('reads only the configured directories, and no junk', function (array $directories): void {
    seedFiles($this->package, [
        'resources/boost/.DS_Store' => 'junk',
        'config/development-settings.php' => '<?php return [];',
    ]);

    $modified = (new ContributionDetector)
        ->modified($this->package, $directories, new Manifest, ['.DS_Store']);

    expect($modified)->toBe([]);
})->with([
    'the configured directory' => [['resources/boost']],
    'no capture directory at all' => [[]],
]);
