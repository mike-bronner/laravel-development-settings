<?php

declare(strict_types=1);

const TIA_WORKFLOW_V1 = <<<YAML
    name: TIA Baseline

    YAML;

const TIA_HAND_WRITTEN = <<<YAML
    name: TIA Baseline
    runs-on: ubuntu-latest

    YAML;

const BEFORE_INSTALL_HOOK = '.github/actions/tia-baseline-before-install';

const AFTER_INSTALL_HOOK = '.github/actions/tia-baseline-after-install';

afterEach(function (): void {
    isset($this->project) && removeTempDir($this->project);
});

it('ships the workflow to every consuming project as a tracked file', function (): void {
    expect(shippedFiles())->toHaveKey(TIA_WORKFLOW_TARGET, TIA_WORKFLOW_SOURCE);
    expect(shippedManifest()->isKnown(TIA_WORKFLOW_TARGET, md5(tiaWorkflow())))->toBeTrue();
});

it('keeps the workflow out of the workflows this repository runs', function (): void {
    expect(REPOSITORY_ROOT . '/' . TIA_WORKFLOW_TARGET)->not
        ->toBeFile();
    expect(TIA_WORKFLOW_SOURCE)->not
        ->toStartWith('.github/');
});

it('creates, updates and protects the workflow like any tracked file', function (
    ?string $local,
    string $expected,
    string $reported,
): void {
    [$this->project] = makeConsumer([
        'manifest' => [TIA_WORKFLOW_TARGET => [md5(TIA_WORKFLOW_V1), md5(tiaWorkflow())]],
        'sources' => [TIA_WORKFLOW_SOURCE => tiaWorkflow()],
        'paths' => ['files' => [TIA_WORKFLOW_SOURCE => TIA_WORKFLOW_TARGET]],
    ]);
    $local === null || seedFiles($this->project, [TIA_WORKFLOW_TARGET => $local]);

    $output = publishIn($this->project);

    expect(file_get_contents("{$this->project}/" . TIA_WORKFLOW_TARGET))->toBe($expected);
    expect($output)->toContain($reported);
})->with([
    'missing: created' => [null, tiaWorkflow(), '+  ' . TIA_WORKFLOW_TARGET],
    'an older version: updated' => [TIA_WORKFLOW_V1, tiaWorkflow(), '↻  ' . TIA_WORKFLOW_TARGET],
    'hand-written: kept' => [
        TIA_HAND_WRITTEN,
        TIA_HAND_WRITTEN,
        TIA_WORKFLOW_TARGET . ' (locally modified)',
    ],
]);

it('records on the default branch alone, on a push or a manual run', function (string $line): void {
    expect(explode("\n", tiaWorkflow()))->toContain($line);
})->with([
    'the branches the sync workflow uses' => ['    branches: [main, master, develop, production]'],
    'a manual run' => ['  workflow_dispatch:'],
    'the default branch' => [
        "    if: github.ref == format('refs/heads/{0}', github.event.repository.default_branch)",
    ],
]);

it('runs on no event the shared action refuses', function (string $event): void {
    expect(tiaWorkflow())->not
        ->toContain("  {$event}:");
})->with(['pull_request', 'pull_request_target', 'pull_request_review', 'workflow_run']);

it('lets a failed recording fail the run', function (): void {
    expect(tiaWorkflow())->not
        ->toContain('continue-on-error');
});

it('runs each project-owned hook only when the project has it', function (
    string $step,
    string $hook,
): void {
    expect(tiaWorkflowStepLines($step))->toContain(
            "if: hashFiles('{$hook}/action.yml', '{$hook}/action.yaml') != ''",
            "uses: ./{$hook}",
            'secrets: ${{ toJSON(secrets) }}',
        );
})->with([
    'before Composer' => ['Set up the project before Composer', BEFORE_INSTALL_HOOK],
    'after Composer' => ['Set up the project after Composer', AFTER_INSTALL_HOOK],
]);

it('runs the generic steps in order, with each hook in its place', function (): void {
    preg_match_all('/^      - name: (.+)$/m', tiaWorkflow(), $names);

    expect(implode("\n", (array) data_get($names, 1)))->toBe(<<<TEXT
        Checkout
        Set up the project before Composer
        Choose the PHP version
        Set up PHP
        Install Composer dependencies
        Set up the project after Composer
        Record and publish the baseline
        TEXT);
});

it('hands the choices of the before-install hook on', function (string $step, string $line): void {
    expect(tiaWorkflowStepLines($step))->toContain($line);
})->with([
    ['Choose the PHP version', 'HOOK_PHP_VERSION: ${{ steps.before-install.outputs.php-version }}'],
    ['Set up PHP', 'php-version: ${{ steps.php.outputs.version }}'],
    ['Set up PHP', 'extensions: ${{ steps.before-install.outputs.php-extensions }}'],
    ['Set up PHP', 'ini-values: ${{ steps.before-install.outputs.php-ini-values }}'],
    ['Set up PHP', 'coverage: pcov'],
    [
        'Install Composer dependencies',
        'run: composer install --no-interaction --no-progress --prefer-dist',
    ],
    [
        'Record and publish the baseline',
        'uses: mike-bronner/laravel-development-settings/.github/actions/tia-baseline@main',
    ],
    [
        'Record and publish the baseline',
        'arguments: ${{ steps.before-install.outputs.pest-arguments }}',
    ],
]);

it('records on the PHP version the hook or composer.json names', function (
    array $composer,
    string $hookVersion,
    string $expected,
): void {
    $this->project = makeTempDir('devset-tia-php-');

    $result = runTiaPhpVersionStep($this->project, (string) json_encode($composer), $hookVersion);

    expect($result->exitCode())->toBe(0, $result->output());
    expect(stepOutputs((string) file_get_contents("{$this->project}/github-output")))
        ->toBe(['version' => $expected]);
    expect($result->output())->toContain("Recording on PHP {$expected}.");
})->with([
    'the hook, over composer.json' => [['require' => ['php' => '^8.4']], '8.5', '8.5'],
    'the platform, over the requirement' => [
        ['require' => ['php' => '^8.3'], 'config' => ['platform' => ['php' => '8.4.12']]],
        '',
        '8.4',
    ],
    'the requirement, when the platform names no minor version' => [
        ['require' => ['php' => '^8.4'], 'config' => ['platform' => ['php' => '8']]],
        '',
        '8.4',
    ],
    'the lowest version a caret allows' => [['require' => ['php' => '^8.4']], '', '8.4'],
    'the first version a range names' => [['require' => ['php' => '>=8.3 <8.6']], '', '8.3'],
]);

it('fails when neither the hook nor composer.json names a PHP version', function (): void {
    $this->project = makeTempDir('devset-tia-php-');

    $composer = (string) json_encode(['require' => ['laravel/framework' => '^12.0']]);

    $result = runTiaPhpVersionStep($this->project, $composer);

    expect($result->exitCode())->toBe(1);
    expect($result->output())->toContain(
            '::error title=Pest TIA baseline::composer.json names no PHP version',
            'Set the php-version output of .github/actions/tia-baseline-before-install.',
        );
    expect((string) file_get_contents("{$this->project}/github-output"))->toBe('');
});
