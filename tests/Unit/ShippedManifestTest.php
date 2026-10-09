<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use MikeBronner\DevelopmentSettings\Support\ContributionDetector;
use MikeBronner\DevelopmentSettings\Support\ManagedSection;
use MikeBronner\DevelopmentSettings\Support\PackageConfig;
use MikeBronner\DevelopmentSettings\Support\ProjectKind;

it('knows the versions older tags shipped', function (string $path, string $checksum): void {
    expect(shippedManifest()->isKnown($path, $checksum))->toBeTrue();
})->with([
    '.gitignore' => ['.gitignore', '91990d02db276ed5bd7a21f2780db4e3'],
    'sync workflow' => [
        '.github/workflows/sync-developer-settings.yml',
        '96799ee1dfa802002dd95c4b5203002d',
    ],
]);

it('ships every managed target as a source the marker can follow', function (): void {
    $managed = shippedConfig()->entries(PackageConfig::MANAGED);
    $sources = collect(shippedFiles())
        ->only($managed)
        ->map(fn (string $source): string => shippedSource($source));

    expect($managed)->not
        ->toBe([]);
    expect($sources->keys()->all())->toBe($managed);
    expect($sources->reject(fn (string $contents): bool => str_ends_with($contents, "\n"))->all())
        ->toBe([]);
    $section = new ManagedSection;

    expect($sources->map(fn (string $contents): int => $section->markers($contents))->sum())
        ->toBe(0);
});

it('ships no testbench.yaml, because bin/rooted-testbench.php does the rooting', function (): void {
    expect(shippedFiles())->not
        ->toHaveKey('testbench.yaml');
    expect(shippedManifest()->paths())->not
        ->toContain('testbench.yaml');
});

it('keeps the retired package files out of the tracked files and manifest.json', function (): void {
    $retired = shippedManifest(ProjectKind::MANIFEST_FILE)->paths();

    expect($retired)->toEqualCanonicalizing([ProjectKind::ARTISAN, '.gitattributes']);
    expect(array_intersect($retired, array_keys(shippedFiles())))->toBe([]);
    expect(array_intersect($retired, shippedManifest()->paths()))->toBe([]);
});

it('tells the last shim it wrote by its constant alone', function (): void {
    $withoutChecksums = new ProjectKind(manifestFile: REPOSITORY_ROOT . '/missing.json');

    expect($withoutChecksums->isShim(shimSource()))->toBeTrue();
});

it('knows every shim checksum, the copy stripped of its marker included', function (): void {
    $known = shippedManifest(ProjectKind::MANIFEST_FILE)->knownChecksums(ProjectKind::ARTISAN);

    expect($known)->toContain(
            md5(previouslyShippedShim()),
            md5(strippedShim()),
            md5(shimSource()),
        );
});

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
