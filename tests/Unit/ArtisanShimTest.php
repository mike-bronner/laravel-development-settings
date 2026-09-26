<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\ManagedSection;
use MikeBronner\DevelopmentSettings\Support\PackageRepository;
use MikeBronner\DevelopmentSettings\Support\SystemProcess;

// A package repository holding the shipped shim. With `testbench`, its
// Testbench is a stand-in that prints the rooting it was booted with.
function shimRepository(bool $testbench): string
{
    $project = makeTempDir('devset-shim-');
    copy(dirname(__DIR__, 2) . '/resources/project/artisan', $project . '/artisan');

    if ($testbench) {
        mkdir($project . '/vendor/bin', 0755, true);
        file_put_contents(
            $project . '/' . PackageRepository::TESTBENCH,
            '<?php echo json_encode(["APP_BASE_PATH" => $_ENV["APP_BASE_PATH"], "APP_ENV" => $_ENV["APP_ENV"], "TESTBENCH_WORKING_PATH" => getenv("TESTBENCH_WORKING_PATH"), "arguments" => array_slice($argv, 1)]);',
        );
    }

    return $project;
}

function runShim(string $project, string $prefix = ''): array
{
    $result = (new SystemProcess)->capture($prefix . escapeshellarg(PHP_BINARY) . ' artisan boost:mcp', $project);

    return [$result->exitCode, $result->output];
}

it('carries the marker that tells it from an app artisan', function (): void {
    $project = shimRepository(testbench: false);

    expect(PackageRepository::isApp($project))->toBeFalse();

    removeTempDir($project);
});

it('boots Testbench rooted at the repository, and creates the directories Testbench needs', function (): void {
    $project = shimRepository(testbench: true);

    [$exitCode, $output] = runShim($project);

    expect($exitCode)->toBe(0)
        ->and(json_decode($output, associative: true))->toBe([
            'APP_BASE_PATH' => realpath($project),
            'APP_ENV' => 'local',
            'TESTBENCH_WORKING_PATH' => realpath($project),
            'arguments' => ['boost:mcp'],
        ])
        ->and(is_dir($project . '/bootstrap/cache'))->toBeTrue()
        ->and(is_dir($project . '/storage/framework/views'))->toBeTrue();

    removeTempDir($project);
});

it('keeps an APP_ENV the caller set', function (): void {
    $project = shimRepository(testbench: true);

    [, $output] = runShim($project, 'APP_ENV=testing ');

    expect(json_decode($output, associative: true)['APP_ENV'])->toBe('testing');

    removeTempDir($project);
});

it('fails with the next step, and creates nothing, when Testbench is not installed', function (): void {
    $project = shimRepository(testbench: false);

    [$exitCode, $output] = runShim($project);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('This artisan runs through Orchestra Testbench, which is not installed.')
        ->and($output)->not->toContain('Fatal')
        ->and(file_exists($project . '/bootstrap'))->toBeFalse()
        ->and(file_exists($project . '/storage'))->toBeFalse();

    removeTempDir($project);
});

// The dist archive Packagist serves is a `git archive` of the tag. The shipped
// .gitattributes, as the sync writes it, has to keep the shim out of it, and
// keep a project's own lines below the marker working.
it('stays out of the dist archive under the .gitattributes the sync writes', function (): void {
    $project = shimRepository(testbench: false);
    file_put_contents($project . '/.gitattributes', ManagedSection::compose(
        managed: (string) file_get_contents(dirname(__DIR__, 2) . '/resources/project/gitattributes'),
        project: "/tests export-ignore\n",
    ));
    mkdir($project . '/tests');
    file_put_contents($project . '/tests/ExampleTest.php', "<?php\n");
    file_put_contents($project . '/composer.json', "{}\n");

    $git = 'git -c user.name=test -c user.email=test@example.com -c commit.gpgsign=false ';
    $process = new SystemProcess;

    foreach (['init -q', 'add -A', 'commit -q -m initial'] as $command) {
        expect($process->run($git . $command, $project))->toBe(0);
    }

    $archived = $process->capture($git . 'archive --format=tar HEAD | tar -t', $project);

    expect($archived->exitCode)->toBe(0)
        ->and(explode("\n", trim($archived->output)))->toEqualCanonicalizing(['.gitattributes', 'composer.json'])
        ->and($process->capture($git . 'ls-files', $project)->output)->toContain('artisan');

    removeTempDir($project);
});
