<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use MikeBronner\DevelopmentSettings\Support\ContributionDetector;
use MikeBronner\DevelopmentSettings\Support\ManagedSection;
use MikeBronner\DevelopmentSettings\Support\PackageConfig;
use MikeBronner\DevelopmentSettings\Support\ProjectKind;

/*
 * Versions shipped at release tags before manifest.json recorded them. The
 * reverse sync proposed them upstream as local edits (PRs #19 and #21), and
 * the plugin protected them as locally modified. They are the tag backfill's
 * regression guard: each was an untouched copy of a released file.
 */
it('knows the versions older tags shipped', function (string $path, string $checksum): void {
    expect(shippedManifest()->isKnown($path, $checksum))->toBeTrue();
})->with([
    '.gitignore' => ['.gitignore', '91990d02db276ed5bd7a21f2780db4e3'],
    'sync workflow' => [
        '.github/workflows/sync-developer-settings.yml',
        '96799ee1dfa802002dd95c4b5203002d',
    ],
]);

/*
 * A managed target is written as its source plus the marker line, so it has
 * to be a tracked file, its source must end with a newline, or the marker
 * would land on its last line, and must not hold the marker itself, or every
 * project would get two and refuse the file from then on.
 */
it('ships every managed target as a source the marker can follow', function (string $group): void {
    $managed = match ($group) {
        'package' => shippedConfig()->entries(PackageConfig::PACKAGE_MANAGED),
        default => shippedConfig()->entries(PackageConfig::MANAGED),
    };
    $sources = collect(shippedFiles($group))
        ->only($managed)
        ->map(fn (string $source): string => shippedSource($source));

    expect($managed)->not
        ->toBe([]);
    expect($sources->keys()->all())->toBe($managed);
    expect($sources->reject(fn (string $contents): bool => str_ends_with($contents, "\n"))->all())
        ->toBe([]);
    $section = new ManagedSection();

    expect($sources->map(fn (string $contents): int => $section->markers($contents))->sum())
        ->toBe(0);
})->with(['paths', 'package']);

it('ships no testbench.yaml, because the artisan shim does the rooting', function (): void {
    expect(shippedFiles('paths'))->not
        ->toHaveKey('testbench.yaml');
    expect(shippedManifest()->paths())->not
        ->toContain('testbench.yaml');
});

/*
 * Every app holds an artisan of its own. Were the shim a tracked file, or
 * known under manifest.json, copy-sync would call every app's artisan locally
 * modified, and orphan cleanup would offer to delete it.
 */
it('keeps the package files out of the tracked files and out of manifest.json', function (): void {
    $targets = array_keys(shippedFiles('package'));

    expect($targets)->toContain(ProjectKind::ARTISAN);
    expect(shippedManifest(ProjectKind::MANIFEST_FILE)->paths())->toEqualCanonicalizing($targets);
    expect(array_intersect($targets, array_keys(shippedFiles('paths'))))->toBe([]);
    expect(array_intersect($targets, shippedManifest()->paths()))->toBe([]);
});

it('ships a shim that carries its marker', function (): void {
    $source = (string) data_get(shippedFiles('package'), ProjectKind::ARTISAN);

    expect((new ProjectKind())->isShim(shippedSource($source)))->toBeTrue();
});

/*
 * Copy-sync and orphan cleanup read manifest.json on project paths. A
 * resources/boost key there would let cleanup delete a consuming package's
 * own resources/boost files.
 */
it('keeps capture checksums out of the manifest copy-sync reads', function (): void {
    $prefixes = collect(shippedConfig()->entries(PackageConfig::CAPTURE))
        ->map(fn (string $directory): string => "{$directory}/")
        ->all();
    $captured = fn (string $path): bool => Str::startsWith($path, $prefixes);
    $capture = collect(shippedManifest(ContributionDetector::MANIFEST_FILE)->paths());

    expect(collect(shippedManifest()->paths())->filter($captured)->all())->toBe([]);
    expect($capture->all())->not
        ->toBe([]);
    expect($capture->reject($captured)->all())->toBe([]);
});
