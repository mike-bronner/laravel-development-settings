<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\ProcessResult;
use MikeBronner\DevelopmentSettings\Support\SystemProcess;

/*
 * Steps of the reverse sync workflow, run as written in its YAML.
 */

const REUSABLE_SYNC = __DIR__ . '/../../.github/workflows/reusable-sync.yml';

const SYNC_STEP = <<<REGEX
    /^          php -r '\n(.*?)\n          '\n/ms
    REGEX;

const DETECT_STEP = <<<REGEX
    /^      - name: Detect changes\n.*?^        run: \|\n(.*?)\n\n      - name:/ms
    REGEX;

const DETECT_STEP_INDENT = 10;

const PULL_REQUEST_BODY = <<<REGEX
    /^          body: \|\n(.*?)\n          commit-message:/ms
    REGEX;

const PULL_REQUEST_BODY_INDENT = 12;

/**
 * The part of the workflow the pattern captures, with the indent it carries
 * inside the YAML taken off every line.
 */
function workflowPart(string $pattern, int $indent = 0): string
{
    $matched = preg_match($pattern, (string) file_get_contents(REUSABLE_SYNC), $match);

    expect($matched)->toBe(1);

    return (string) preg_replace("/^ {{$indent}}/m", '', (string) data_get($match, 1));
}

/**
 * A project whose `.gitignore` carries an edit above the marker, beside a
 * package checkout built from this repository's sources, answered as the
 * project, the package checkout and the shipped .gitignore rules.
 *
 * @return array{string, string, string}
 */
function syncFixture(): array
{
    $project = makeTempDir('devset-sync-');
    $package = "{$project}/_laravel-development-settings";
    $shipped = shippedSource('resources/project/gitignore');
    $sources = escapeshellarg(REPOSITORY_ROOT . '/src');

    seedFiles($package, [
        'config/development-settings.php' => shippedSource('config/development-settings.php'),
        'manifest.json' => shippedSource('manifest.json'),
        'resources/project/gitignore' => $shipped,
    ]);
    (new SystemProcess)->run("cp -R {$sources} src", $package);
    file_put_contents("{$project}/.gitignore", marked("{$shipped}/edited\n", "\n!AGENTS.md\n"));

    return [$project, $package, $shipped];
}

/**
 * Run the workflow's own PHP step in the project.
 */
function runSyncStep(string $project): ProcessResult
{
    $script = escapeshellarg(workflowPart(SYNC_STEP));

    return (new SystemProcess)->capture(escapeshellarg(PHP_BINARY) . " -r {$script}", $project);
}

/**
 * Run the workflow's change detection in the project, and answer the step
 * outputs by name.
 *
 * @return array<string, string>
 */
function runDetectStep(string $project): array
{
    $script = escapeshellarg(workflowPart(DETECT_STEP, DETECT_STEP_INDENT));
    $outputFile = "{$project}/github-output";
    $quotedOutputFile = escapeshellarg($outputFile);
    touch($outputFile);

    $result = (new SystemProcess)
        ->capture("GITHUB_OUTPUT={$quotedOutputFile} bash -e -c {$script}", $project);

    expect($result->exitCode())->toBe(0, $result->output());

    return stepOutputs((string) file_get_contents($outputFile));
}

/**
 * The outputs a step wrote to `GITHUB_OUTPUT`: `name=value` lines, and
 * `name<<DELIMITER` blocks.
 *
 * @return array<string, string>
 */
function stepOutputs(string $written): array
{
    preg_match_all('/^(\w+)=(.*)$/m', $written, $pairs);
    preg_match_all('/^(\w+)<<(\S+)\n(.*?)\n\2$/ms', $written, $blocks);
    [, $pairNames, $pairValues] = $pairs;
    [, $blockNames, , $blockValues] = $blocks;

    return [...array_combine($pairNames, $pairValues), ...array_combine($blockNames, $blockValues)];
}

/**
 * The PR body as the workflow writes it, with the step outputs filled in.
 *
 * @param  array<string, string>  $outputs
 */
function renderPullRequestBody(array $outputs): string
{
    $body = (string) preg_replace_callback(
            '/\$\{\{ steps\.changes\.outputs\.(\w+) \}\}/',
            fn (array $name): string => (string) data_get($outputs, [data_get($name, 1)]),
            workflowPart(PULL_REQUEST_BODY, PULL_REQUEST_BODY_INDENT),
        );

    return (string) preg_replace('/\$\{\{ [^}]+ \}\}/', 'owner/project', $body);
}
