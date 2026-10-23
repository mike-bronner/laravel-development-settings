<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\PackageConfig;
use MikeBronner\DevelopmentSettings\Support\ProcessResult;
use MikeBronner\DevelopmentSettings\Support\SystemProcess;

const TIA_ACTION = __DIR__ . '/../../.github/actions/tia-baseline';

const TIA_GRAPH = <<<JSON
    {"graph":"recorded"}
    JSON;

const TIA_DEFAULT_BRANCH = 'main';

const TIA_WORKFLOW_SOURCE = 'resources/project/tia-baseline.yml';

const TIA_WORKFLOW_TARGET = '.github/workflows/tia-baseline.yml';

const TIA_WORKFLOW_V1 = <<<YAML
    name: TIA Baseline

    YAML;

const TIA_PROJECT_ENV = <<<YAML
    env:
      COMPOSER_AUTH: '{"github-oauth": {"github.com": "\${{ secrets.WORKFLOW_PAT }}"}}'
      NOVA_USERNAME: \${{ secrets.NOVA_USERNAME }}
      NOVA_LICENSE_KEY: \${{ secrets.NOVA_LICENSE_KEY }}
      SPARK_USERNAME: \${{ secrets.SPARK_USERNAME }}
      SPARK_API_TOKEN: \${{ secrets.SPARK_API_TOKEN }}
      FLUX_USERNAME: \${{ secrets.FLUX_USERNAME }}
      FLUX_LICENSE_KEY: \${{ secrets.FLUX_LICENSE_KEY }}

    YAML;

const TIA_OVERRIDE_ENV = <<<YAML
    env:
      TIA_POSTGRES_VERSION: '16'
      TIA_REDIS_VERSION: '7.4'
      DB_DATABASE: app_testing
      DB_USERNAME: app
      DB_PASSWORD: secret

    YAML;

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

function fakeXdebug(string $mode): string
{
    $modes = var_export([$mode], true);

    return <<<PHP
        <?php
        function xdebug_start_code_coverage(): void {}
        function xdebug_info(string \$category = ''): array { return {$modes}; }
        PHP;
}

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

    return (new SystemProcess)->capture("{$assignments->implode(' ')} bash {$script}", $project);
}

function pestRan(string $project): bool
{
    return is_file("{$project}/pest-arguments");
}

function pestRunArguments(string $project): string
{
    return trim((string) file_get_contents("{$project}/pest-arguments"));
}

function stagedBaseline(string $project): string
{
    return "{$project}/runner/pest-tia-baseline";
}

function actionStep(string $name): string
{
    $pattern = '/^    - name: ' . preg_quote($name, '/') . '\n(.*?)(?:\n\n|\z)/ms';

    preg_match($pattern, (string) file_get_contents(TIA_ACTION . '/action.yml'), $step);

    return (string) data_get($step, 1);
}

function tiaWorkflow(): string
{
    return shippedSource(TIA_WORKFLOW_SOURCE);
}

function tiaWorkflowStep(string $name): string
{
    $pattern = '/^      - name: ' . preg_quote($name, '/') . '\n(.*?)(?=\n+      -|\n*\z)/ms';

    preg_match($pattern, tiaWorkflow(), $step);

    return (string) data_get($step, 1);
}

function tiaWorkflowStepLines(string $name): array
{
    return collect(explode("\n", tiaWorkflowStep($name)))
        ->map(fn (string $line): string => trim($line))
        ->all();
}

function tiaWorkflowStepScript(string $name): string
{
    $matched = preg_match('/^        run: \|\n(.*)/ms', tiaWorkflowStep($name), $match);

    expect($matched)->toBe(1);

    return (string) preg_replace('/^ {10}/m', '', (string) data_get($match, 1));
}

function runTiaMaskStep(string $secret): ProcessResult
{
    $script = escapeshellarg(tiaWorkflowStepScript('Mask the hook secrets'));
    $environment = 'TIA_BASELINE_SECRETS=' . escapeshellarg($secret);

    return (new SystemProcess)
        ->capture("{$environment} bash -e -o pipefail -c {$script}", sys_get_temp_dir());
}

function runTiaPhpVersionStep(
    string $project,
    string $composerJson,
    string $hookVersion = '',
): ProcessResult {
    $script = tiaWorkflowStepScript('Choose the PHP version');
    seedFiles($project, ['composer.json' => $composerJson, 'github-output' => '']);
    $environment = collect([
        'HOOK_PHP_VERSION' => $hookVersion,
        'GITHUB_OUTPUT' => "{$project}/github-output",
    ])->map(fn (string $value, string $name): string => "{$name}=" . escapeshellarg($value));

    return (new SystemProcess)
        ->capture("{$environment->implode(' ')} bash -e -c " . escapeshellarg($script), $project);
}

function tiaWorkflowConsumer(?string $local): string
{
    [$project] = makeConsumer([
        'manifest' => [TIA_WORKFLOW_TARGET => [md5(TIA_WORKFLOW_V1), md5(tiaWorkflow())]],
        'sources' => [TIA_WORKFLOW_SOURCE => tiaWorkflow()],
        'paths' => [
            'files' => [TIA_WORKFLOW_SOURCE => TIA_WORKFLOW_TARGET],
            'managed_project_first' => shippedConfig()
                ->entries(PackageConfig::MANAGED_PROJECT_FIRST),
        ],
    ]);
    $local === null || seedFiles($project, [TIA_WORKFLOW_TARGET => $local]);

    return $project;
}
