<?php

declare(strict_types=1);

use Composer\IO\BufferIO;
use MikeBronner\DevelopmentSettings\ComposerPlugin;
use MikeBronner\DevelopmentSettings\Support\GuidelineGuard;

/**
 * A project shaped the way composition requires: an `artisan` entry point, and
 * Boost present in vendor.
 */
function makeComposableProject(string $agentFile): string
{
    $project = makeTempDir('devset-boost-');

    mkdir($project . '/vendor/laravel/boost', 0755, true);
    file_put_contents($project . '/artisan', "#!/usr/bin/env php\n");
    file_put_contents($project . '/CLAUDE.md', $agentFile);

    return $project;
}

/**
 * A command that only records having run stands in for the Boost command, so
 * the marker file answers whether composition was attempted.
 */
function runBoostOn(string $project): BufferIO
{
    $io = new BufferIO;

    (new ReflectionMethod(ComposerPlugin::class, 'runBoost'))->invoke(
        new ComposerPlugin,
        $io,
        $project,
        [
            'hooks' => [
                // The trailing `--` hands the appended feature flags to the
                // script, not to PHP.
                'command' => escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('touch(' . var_export($project . '/composed.marker', true) . ');') . ' --',
                'description' => 'Updating Laravel Boost...',
            ],
        ],
    );

    return $io;
}

function composed(string $project): bool
{
    return file_exists($project . '/composed.marker');
}

it('composes into an agent file Boost already manages', function (): void {
    $project = makeComposableProject(
        "# CLAUDE.md\n\nProject notes.\n\n"
            . GuidelineGuard::OPENING_TAG . "\n=== rules ===\n\n" . GuidelineGuard::CLOSING_TAG . "\n",
    );

    $io = runBoostOn($project);

    expect(composed($project))->toBeTrue()
        ->and($io->getOutput())->toContain('Updating Laravel Boost...');

    removeTempDir($project);
});

it('refuses to compose when an agent file would be damaged, and says which and why', function (): void {
    $project = makeComposableProject(
        "# CLAUDE.md\n\nBoost replaces the block between " . GuidelineGuard::OPENING_TAG . " and its closing tag.\n\n"
            . GuidelineGuard::OPENING_TAG . "\n=== rules ===\n\n" . GuidelineGuard::CLOSING_TAG . "\n",
    );

    $output = runBoostOn($project)->getOutput();

    // The tag is named in full. Composer renders through Symfony's output
    // formatter, which would be free to read it as a style tag and drop it.
    expect(composed($project))->toBeFalse()
        ->and($output)->toContain('CLAUDE.md')
        ->and($output)->toContain('overwrite hand-written content')
        ->and($output)->toContain(GuidelineGuard::OPENING_TAG)
        ->and($output)->toContain('between the first tag and the next closing tag')
        ->and($output)->not->toContain('Updating Laravel Boost...');

    removeTempDir($project);
});

it('leaves the damaged agent file exactly as it found it', function (): void {
    $agentFile = "# CLAUDE.md\n\nProse naming " . GuidelineGuard::OPENING_TAG . " here.\n\n"
        . GuidelineGuard::OPENING_TAG . "\n=== rules ===\n\n" . GuidelineGuard::CLOSING_TAG . "\n";
    $project = makeComposableProject($agentFile);

    runBoostOn($project);

    // Repair is never attempted. Where the hand-written section ends cannot be
    // read from the file, and a wrong guess destroys the same content.
    expect(file_get_contents($project . '/CLAUDE.md'))->toBe($agentFile);

    removeTempDir($project);
});

it('tells a project with neither artisan nor Testbench why Boost did not run', function (): void {
    $project = makeComposableProject('# CLAUDE.md');
    unlink($project . '/artisan');

    expect(runBoostOn($project)->getOutput())->toContain('no artisan and no vendor/bin/testbench, so Laravel Boost was not run')
        ->and(composed($project))->toBeFalse();

    removeTempDir($project);
});

it('stays quiet while Boost is still queued for installation', function (): void {
    $project = makeComposableProject('# CLAUDE.md');
    rmdir($project . '/vendor/laravel/boost');

    expect(runBoostOn($project)->getOutput())->toBe('')
        ->and(composed($project))->toBeFalse();

    removeTempDir($project);
});
