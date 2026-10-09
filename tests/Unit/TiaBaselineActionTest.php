<?php

declare(strict_types=1);

afterEach(function (): void {
    isset($this->project) && removeTempDir($this->project);
});

it('records with a fresh TIA run and stages the graph at the artifact root', function (): void {
    $this->project = tiaProject();

    $result = runTiaAction($this->project, '--parallel --processes=4');

    expect($result->exitCode())->toBe(0, $result->output());
    expect(pestRunArguments($this->project))->toBe('--tia --fresh --parallel --processes=4');
    expect(scandir(stagedBaseline($this->project)))->toBe(['.', '..', 'graph.json']);
    expect(file_get_contents(stagedBaseline($this->project) . '/graph.json'))->toBe(TIA_GRAPH);
    expect(stepOutputs((string) file_get_contents("{$this->project}/github-output")))
        ->toBe(['path' => stagedBaseline($this->project)]);
    expect($result->output())->toContain('Recording the Pest TIA graph with xdebug (Pest 5.2.1).');
});

it('runs Pest with TIA alone when no arguments are given', function (): void {
    $this->project = tiaProject();

    $result = runTiaAction($this->project);

    expect($result->exitCode())->toBe(0, $result->output());
    expect(pestRunArguments($this->project))->toBe('--tia --fresh');
});

it('fails when Pest is not installed', function (): void {
    $this->project = tiaProject();
    unlink("{$this->project}/vendor/bin/pest");

    $result = runTiaAction($this->project);

    expect($result->exitCode())->toBe(1);
    expect($result->output())->toContain('vendor/bin/pest is missing.');
});

it('fails before the run', function (array $options, array $run, string $message): void {
    $this->project = tiaProject($options);

    $result = runTiaAction($this->project, ...$run);

    expect($result->exitCode())->toBe(1);
    expect($result->output())->toContain("::error title=Pest TIA baseline::{$message}");
    expect(pestRan($this->project))->toBeFalse();
})->with([
    'another branch' => [[], ['ref' => 'refs/heads/feature'], 'This run is on refs/heads/feature.'],
    'no default branch' => [
        ['event' => ['repository' => 'owner/project']],
        [],
        'The event payload names no default branch',
    ],
    'pull_request_target' => [
        [],
        ['event' => 'pull_request_target'],
        'This run was triggered by pull_request_target.',
    ],
    'pull_request' => [[], ['event' => 'pull_request'], 'This run was triggered by pull_request.'],
    'workflow_run' => [[], ['event' => 'workflow_run'], 'This run was triggered by workflow_run.'],
    'Pest 3' => [['pest' => '3.8.7'], [], 'Pest 3.8.7 has no Test Impact Analysis.'],
    'Pest 4' => [['pest' => '4.9.0'], [], 'Pest 4.9.0 has no Test Impact Analysis.'],
    'a failing --version' => [['versionExit' => 1], [], 'vendor/bin/pest --version failed.'],
    'an unreadable version' => [['pest' => 'dev-main'], [], 'Could not read the Pest version'],
    'no storage directory' => [['printedStorage' => ''], [], 'Pest printed no storage directory'],
    'no driver' => [['driver' => NO_DRIVER], [], 'No coverage driver.'],
    'Xdebug off coverage mode' => [['driver' => XDEBUG_DEVELOP], [], 'No coverage driver.'],
]);

it('records on Pest 5 and later, on a push, a manual or a scheduled run', function (
    array $options,
    array $run,
): void {
    $this->project = tiaProject($options);

    $result = runTiaAction($this->project, ...$run);

    expect($result->exitCode())->toBe(0, $result->output());
    expect(pestRunArguments($this->project))->toBe('--tia --fresh');
})->with([
    'Pest 5.0.0' => [['pest' => '5.0.0'], []],
    'Pest 6.1.2' => [['pest' => '6.1.2'], []],
    'workflow_dispatch' => [[], ['event' => 'workflow_dispatch']],
    'schedule' => [[], ['event' => 'schedule']],
]);

it('records through pcov when it is enabled, and fails when it is not', function (): void {
    $this->project = tiaProject(['driver' => PCOV_ENABLED]);
    $enabled = runTiaAction($this->project);
    removeTempDir($this->project);
    $this->project = tiaProject(['driver' => PCOV_DISABLED]);

    $disabled = runTiaAction($this->project);

    expect($enabled->exitCode())->toBe(0, $enabled->output());
    expect($enabled->output())->toContain('Recording the Pest TIA graph with pcov');
    expect($disabled->exitCode())->toBe(1);
    expect($disabled->output())->toContain('No coverage driver.');
})->skip(
        ! extension_loaded('pcov') || extension_loaded('xdebug'),
        'Needs the pcov extension, without Xdebug.',
    );

it('stages nothing when the run writes no graph', function (array $options, string $cause): void {
    $this->project = tiaProject($options);
    seedFiles("{$this->project}/storage", ['graph.json' => 'stale']);

    $result = runTiaAction($this->project);

    expect($result->exitCode())->toBe(1);
    expect($result->output())->toContain($cause);
    expect(file_exists(stagedBaseline($this->project)))->toBeFalse();
})->with([
    'a failed test run' => [['exit' => 1], 'The test run failed, so no baseline is published.'],
    'a run that writes no graph' => [['graph' => null], 'Pest wrote no graph.json to'],
    'a run that writes an empty graph' => [['graph' => ''], 'Pest wrote no graph.json to'],
]);

it('wires the record and upload steps of the action', function (string $name, string $line): void {
    $lines = collect(explode("\n", actionStep($name)))
        ->map(fn (string $stepLine): string => trim($stepLine));

    expect($lines->all())->toContain($line);
})->with([
    ['Record the Pest TIA graph', 'TIA_BASELINE_ARGUMENTS: ${{ inputs.arguments }}'],
    ['Record the Pest TIA graph', "run: bash \"\$GITHUB_ACTION_PATH/record.sh\""],
    ['Upload the Pest TIA baseline', 'uses: actions/upload-artifact@v4'],
    ['Upload the Pest TIA baseline', 'name: pest-tia-baseline'],
    ['Upload the Pest TIA baseline', 'path: ${{ steps.record.outputs.path }}'],
    ['Upload the Pest TIA baseline', 'if-no-files-found: error'],
]);
