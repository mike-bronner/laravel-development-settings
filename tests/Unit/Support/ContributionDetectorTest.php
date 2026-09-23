<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\ContributionDetector;
use MikeBronner\DevelopmentSettings\Support\Manifest;

function writeSource(string $packageDir, string $relativePath, string $content): void
{
    if (! is_dir(dirname($packageDir . '/' . $relativePath))) {
        mkdir(dirname($packageDir . '/' . $relativePath), 0755, true);
    }

    file_put_contents($packageDir . '/' . $relativePath, $content);
}

it('detects installed sources edited away from every shipped version', function (): void {
    $package = makeTempDir();
    writeSource($package, 'resources/boost/guidelines/edited.md', 'locally changed');
    writeSource($package, 'resources/boost/guidelines/pristine.md', 'shipped');
    writeSource($package, 'resources/boost/skills/older/SKILL.md', 'shipped last release');

    $sources = new Manifest([
        'resources/boost/guidelines/edited.md' => [md5('original')],
        'resources/boost/guidelines/pristine.md' => [md5('shipped')],
        'resources/boost/skills/older/SKILL.md' => [md5('shipped last release'), md5('shipped now')],
    ]);

    $modified = (new ContributionDetector)->modified($package, ['resources/boost'], $sources);

    expect($modified)->toBe([
        'resources/boost/guidelines/edited.md' => $package . '/resources/boost/guidelines/edited.md',
    ]);

    removeTempDir($package);
});

it('detects a source the developer added, which no release ever shipped', function (): void {
    $package = makeTempDir();
    writeSource($package, 'resources/boost/skills/new-skill/SKILL.md', 'new');

    $modified = (new ContributionDetector)->modified($package, ['resources/boost'], new Manifest);

    expect(array_keys($modified))->toBe(['resources/boost/skills/new-skill/SKILL.md']);

    removeTempDir($package);
});

it('reads only the configured directories and skips junk files', function (): void {
    $package = makeTempDir();
    writeSource($package, 'resources/boost/.DS_Store', 'junk');
    writeSource($package, 'config/development-settings.php', '<?php return [];');

    $modified = (new ContributionDetector)->modified($package, ['resources/boost'], new Manifest, ['.DS_Store']);

    expect($modified)->toBe([]);

    removeTempDir($package);
});

it('returns nothing when no capture directory is configured', function (): void {
    $package = makeTempDir();
    writeSource($package, 'resources/boost/guidelines/edited.md', 'locally changed');

    expect((new ContributionDetector)->modified($package, [], new Manifest))->toBe([]);

    removeTempDir($package);
});
