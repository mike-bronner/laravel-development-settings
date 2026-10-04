<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\ProcessResult;
use MikeBronner\DevelopmentSettings\Support\SystemProcess;

/*
 * The shared Pest TIA baseline action, run in a stand-in consuming
 * repository: a fake vendor/bin/pest, and a `php` on the PATH whose coverage
 * driver the test chooses.
 */

const TIA_ACTION = __DIR__ . '/../../.github/actions/tia-baseline';

const TIA_GRAPH = <<<JSON
    {"graph":"recorded"}
    JSON;

const TIA_DEFAULT_BRANCH = 'main';

const TIA_EXECUTABLE = 0755;

const XDEBUG_COVERAGE = 'coverage';

const XDEBUG_DEVELOP = 'develop';

const PCOV_ENABLED = 'pcov enabled';

const PCOV_DISABLED = 'pcov disabled';

const NO_DRIVER = 'no driver';

const TIA_DEFAULTS = [
    'pest' => '5.2.1',
    'driver' => XDEBUG_COVERAGE,
    'graph' => TIA_GRAPH,
    'exit' => 0,
    'versionExit' => 0,
    'event' => ['repository' => ['default_branch' => TIA_DEFAULT_BRANCH]],
];

/**
 * A consuming repository with Pest installed, answered as its root. Each
 * option replaces one default: the Pest version, the coverage driver, the
 * graph the Pest run writes (`null` for none), its exit code, the exit code of
 * `--version`, and the event payload. `printedStorage` replaces the directory
 * `--baseline` prints.
 *
 * @param  array<string, mixed>  $options
 */
function tiaProject(array $options = []): string
{
    $settings = [...TIA_DEFAULTS, ...$options];
    $project = makeTempDir('devset-tia-');

    seedFiles($project, [
        'event.json' => (string) json_encode(data_get($settings, 'event')),
        'bin/php' => fakePhp((string) data_get($settings, 'driver'), $project),
        'fake-xdebug.php' => fakeXdebug((string) data_get($settings, 'driver')),
        'vendor/bin/pest' => fakePest($settings, "{$project}/storage"),
        'recorded-graph' => (string) data_get($settings, 'graph'),
        'storage/.gitkeep' => '',
        'runner/.gitkeep' => '',
        'github-output' => '',
    ]);
    chmod("{$project}/bin/php", TIA_EXECUTABLE);
    chmod("{$project}/vendor/bin/pest", TIA_EXECUTABLE);
    data_get($settings, 'graph') !== null || unlink("{$project}/recorded-graph");

    return $project;
}

/**
 * A `php` that runs this PHP binary with the coverage driver the test chose.
 * `-n` drops every real extension, so only the fake Xdebug functions are
 * there. The pcov cases need the real extension and keep the loaded ini.
 */
function fakePhp(string $driver, string $project): string
{
    $binary = escapeshellarg(PHP_BINARY);
    $prepend = escapeshellarg("{$project}/fake-xdebug.php");

    $options = match ($driver) {
        PCOV_ENABLED => '-d pcov.enabled=1',
        PCOV_DISABLED => '-d pcov.enabled=0',
        NO_DRIVER => '-n',
        default => "-n -d auto_prepend_file={$prepend}",
    };

    return "#!/bin/sh\nexec {$binary} {$options} \"\$@\"\n";
}

/**
 * The Xdebug functions Pest checks for, reporting the mode the test chose.
 */
function fakeXdebug(string $mode): string
{
    $modes = var_export([$mode], true);

    return <<<PHP
        <?php
        function xdebug_start_code_coverage(): void {}
        function xdebug_info(string \$category = ''): array { return {$modes}; }
        PHP;
}

/**
 * A vendor/bin/pest that prints the version as Pest does, colors included,
 * prints the storage directory for `--baseline`, and otherwise records its
 * arguments, writes the graph and exits as the test chose.
 *
 * @param  array<string, mixed>  $settings
 */
function fakePest(array $settings, string $storage): string
{
    $version = escapeshellarg((string) data_get($settings, 'pest'));
    $storageDirectory = escapeshellarg($storage);
    $printedStorage = escapeshellarg((string) data_get($settings, 'printedStorage', $storage));
    $exit = (int) data_get($settings, 'exit');
    $versionExit = (int) data_get($settings, 'versionExit');
    $colored = '\\033[34;1m%s\\033[39;22m';
    $printed = "\\n  Pest Testing Framework {$colored}.  \\n\\n";

    return <<<BASH
        #!/usr/bin/env bash
        case "\${1:-}" in
            --version) printf '{$printed}' {$version}; exit {$versionExit} ;;
            --baseline) echo {$printedStorage}; exit 0 ;;
        esac
        echo "\$*" > pest-arguments
        [ -f recorded-graph ] && cp recorded-graph {$storageDirectory}/graph.json
        exit {$exit}

        BASH;
}

/**
 * Run the action's script in the project as GitHub runs it, on a push unless
 * the test names another event.
 */
function runTiaAction(
    string $project,
    string $arguments = '',
    string $ref = 'refs/heads/main',
    string $event = 'push',
): ProcessResult {
    $environment = collect([
        'PATH' => "{$project}/bin:" . getenv('PATH'),
        'GITHUB_EVENT_PATH' => "{$project}/event.json",
        'GITHUB_EVENT_NAME' => $event,
        'GITHUB_REF' => $ref,
        'GITHUB_OUTPUT' => "{$project}/github-output",
        'RUNNER_TEMP' => "{$project}/runner",
        'TIA_BASELINE_ARGUMENTS' => $arguments,
    ]);
    $assignments = $environment
        ->map(fn (string $value, string $name): string => "{$name}=" . escapeshellarg($value));
    $script = escapeshellarg(TIA_ACTION . '/record.sh');

    return (new SystemProcess())->capture("{$assignments->implode(' ')} bash {$script}", $project);
}

function pestRan(string $project): bool
{
    return is_file("{$project}/pest-arguments");
}

/**
 * The arguments the Pest run was given.
 */
function pestRunArguments(string $project): string
{
    return trim((string) file_get_contents("{$project}/pest-arguments"));
}

function stagedBaseline(string $project): string
{
    return "{$project}/runner/pest-tia-baseline";
}

/**
 * One step of the action, without its name line.
 */
function actionStep(string $name): string
{
    $pattern = '/^    - name: ' . preg_quote($name, '/') . '\n(.*?)(?:\n\n|\z)/ms';

    preg_match($pattern, (string) file_get_contents(TIA_ACTION . '/action.yml'), $step);

    return (string) data_get($step, 1);
}
