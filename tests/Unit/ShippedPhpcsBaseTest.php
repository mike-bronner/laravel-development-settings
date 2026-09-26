<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\FileDiscovery;
use MikeBronner\DevelopmentSettings\Support\FileSync;
use MikeBronner\DevelopmentSettings\Support\Manifest;

const PHPCS_ELSE_SNIFF = 'CleanCode.Conditionals.DisallowElse.Found';

const PHPCS_ELSE_SOURCE = <<<'PHP'
    <?php

    declare(strict_types=1);

    if (true) {
        echo 'a';
    } else {
        echo 'b';
    }

    PHP;

function phpcsProject(array $relativePaths): string
{
    $project = makeTempDir('devset-phpcs-');

    copy(dirname(__DIR__, 2) . '/phpcs.xml', $project . '/phpcs.xml');

    foreach ($relativePaths as $relativePath) {
        $directory = dirname($project . '/' . $relativePath);

        if (! is_dir($directory)) {
            mkdir(directory: $directory, recursive: true);
        }

        file_put_contents($project . '/' . $relativePath, PHPCS_ELSE_SOURCE);
    }

    return $project;
}

// Runs PHP_CodeSniffer the way a developer does, with no arguments, so it
// finds its ruleset and its paths on its own. Returns each checked file,
// relative to the project, with the sniff codes reported against it.
function runPhpcs(string $project): array
{
    $command = [PHP_BINARY, dirname(__DIR__, 2) . '/vendor/bin/phpcs', '-q', '--report=json'];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $project);
    $output = (string) stream_get_contents($pipes[1]);
    $errors = (string) stream_get_contents($pipes[2]);
    proc_close($process);

    $report = json_decode($output, associative: true);

    expect($report)->toBeArray("PHP_CodeSniffer printed no report: {$output}{$errors}");

    $files = [];

    foreach ($report['files'] as $path => $file) {
        $relativePath = substr(realpath($path), strlen(realpath($project)) + 1);
        $files[$relativePath] = array_values(array_unique(array_column($file['messages'], 'source')));
    }

    ksort($files);

    return $files;
}

it('ships phpcs.xml and never a ruleset copy', function (): void {
    $root = dirname(__DIR__, 2);
    $paths = (require $root . '/config/development-settings.php')['paths'];
    $targets = [
        ...array_keys(FileDiscovery::trackedPaths($paths['files'])),
        ...array_keys(FileDiscovery::trackedPaths($paths['directories'])),
    ];

    expect($targets)->toContain('phpcs.xml')
        ->not->toContain('phpcs.xml.dist')
        ->and(array_filter($targets, fn (string $target): bool => str_starts_with($target, '.php-codesniffer')))->toBe([]);
});

it('checks every project file with CleanCode and skips the generated directories', function (array $shipped, array $skipped): void {
    $project = phpcsProject([...$shipped, ...$skipped]);

    $checked = runPhpcs($project);

    removeTempDir($project);

    sort($shipped);

    expect(array_keys($checked))->toBe($shipped);

    foreach ($checked as $sources) {
        expect($sources)->toContain(PHPCS_ELSE_SNIFF);
    }
})->with([
    'application' => [
        ['app/Models/User.php', 'config/app.php', 'database/seeders/DatabaseSeeder.php', 'resources/views/home.blade.php', 'routes/web.php', 'tests/Feature/HomeTest.php'],
        ['bootstrap/cache/services.php', 'node_modules/tool/index.php', 'public/index.php', 'storage/framework/views/compiled.php', 'vendor/acme/lib/Lib.php'],
    ],
    'package' => [
        ['src/Service.php', 'tests/Unit/ServiceTest.php'],
        ['vendor/acme/lib/Lib.php'],
    ],
    // The exclusions anchor on the project root, so a nested directory that
    // shares a name with one of them is still the project's own code.
    'nested names' => [
        ['app/Http/public/Page.php', 'src/storage/Disk.php', 'src/vendor/Vendor.php'],
        [],
    ],
]);

/*
 * Releases before 0.3.3 shipped phpcs.xml pointing at a ruleset that no longer
 * ships. Their unmodified copies are known versions, so the sync replaces them
 * with the current file instead of keeping them as local edits.
 */
it('replaces an unmodified phpcs.xml from an older release', function (): void {
    $root = dirname(__DIR__, 2);
    $project = makeTempDir();
    file_put_contents($project . '/phpcs.xml', <<<'XML'
        <?xml version="1.0"?>
        <ruleset>
            <rule ref="./.php-codesniffer/MikeBronner/ruleset.xml" />
        </ruleset>

        XML);

    $scan = (new FileSync(Manifest::load($root . '/manifest.json')))->classify($project, ['phpcs.xml' => $root . '/phpcs.xml']);

    removeTempDir($project);

    expect(array_keys($scan['updatable']))->toBe(['phpcs.xml']);
});

it('still knows the first phpcs.xml version the manifest recorded', function (): void {
    $manifest = Manifest::load(dirname(__DIR__, 2) . '/manifest.json');

    expect($manifest->isKnown('phpcs.xml', '992e3c5d1d7863fcf21d73374254a46a'))->toBeTrue();
});
