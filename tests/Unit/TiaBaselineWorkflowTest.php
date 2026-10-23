<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\PackageConfig;
use Symfony\Component\Yaml\Yaml;

const TIA_HAND_WRITTEN = <<<YAML
    name: TIA Baseline
    runs-on: ubuntu-latest

    YAML;

const BEFORE_INSTALL_HOOK = '.github/actions/tia-baseline-before-install';

const AFTER_INSTALL_HOOK = '.github/actions/tia-baseline-after-install';

const SECRET_PIN = 4_821;

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

it('keeps the project part of the workflow above its marker', function (): void {
    expect(shippedConfig()->entries(PackageConfig::MANAGED_PROJECT_FIRST))
        ->toBe([TIA_WORKFLOW_TARGET])
        ->and(shippedConfig()->entries(PackageConfig::MANAGED))
        ->toBe(['.gitignore']);
});

it('creates, updates and protects the workflow below its marker', function (
    ?string $local,
    string $expected,
    array $reported,
): void {
    $this->project = tiaWorkflowConsumer($local);

    $output = publishIn($this->project);

    expect(file_get_contents("{$this->project}/" . TIA_WORKFLOW_TARGET))->toBe($expected);
    expect($output)->toContain(...$reported);
})->with([
    'missing: created' => [
        null,
        markedFirst('', "\n" . tiaWorkflow()),
        ['+  ' . TIA_WORKFLOW_TARGET],
    ],
    'an unmarked older version: updated' => [
        TIA_WORKFLOW_V1,
        markedFirst('', "\n" . tiaWorkflow()),
        ['↻  ' . TIA_WORKFLOW_TARGET],
    ],
    'the env block, an older version below: updated' => [
        markedFirst(TIA_PROJECT_ENV, "\n" . TIA_WORKFLOW_V1),
        markedFirst(TIA_PROJECT_ENV, "\n" . tiaWorkflow()),
        ['↻  ' . TIA_WORKFLOW_TARGET, '1 updated'],
    ],
    'the env block, the current version below: unchanged' => [
        markedFirst(TIA_PROJECT_ENV, "\n" . tiaWorkflow()),
        markedFirst(TIA_PROJECT_ENV, "\n" . tiaWorkflow()),
        ['1 unchanged'],
    ],
    'hand-written: kept' => [
        TIA_HAND_WRITTEN,
        TIA_HAND_WRITTEN,
        [TIA_WORKFLOW_TARGET . ' (locally modified, no sync marker)', '1 skipped'],
    ],
    'the marker twice: kept' => [
        markedFirst(TIA_PROJECT_ENV) . markedFirst('', "\n" . tiaWorkflow()),
        markedFirst(TIA_PROJECT_ENV) . markedFirst('', "\n" . tiaWorkflow()),
        [TIA_WORKFLOW_TARGET . ' holds the sync marker more than once', '1 skipped'],
    ],
]);

it('never reports the env block as a local modification', function (): void {
    $this->project = tiaWorkflowConsumer(markedFirst(TIA_PROJECT_ENV, "\n" . TIA_WORKFLOW_V1));

    expect(publishIn($this->project))->not
        ->toContain('locally modified');
});

it('never offers to move an edited workflow above its marker', function (): void {
    $this->project = tiaWorkflowConsumer(TIA_HAND_WRITTEN);

    $output = withKeyPresses([], fn (): string => publishIn($this->project, INTERACTIVE));

    expect(file_get_contents("{$this->project}/" . TIA_WORKFLOW_TARGET))->toBe(TIA_HAND_WRITTEN);
    expect($output)->toContain(
            TIA_WORKFLOW_TARGET . ' is locally modified and has no sync marker',
            'delete it and run composer again',
        );
    expect($output)->not
        ->toContain('Add the sync marker');
});

it('syncs the env block into one workflow with a top-level env', function (): void {
    $this->project = tiaWorkflowConsumer(markedFirst(TIA_PROJECT_ENV, "\n" . TIA_WORKFLOW_V1));
    publishIn($this->project);

    $workflow = Yaml::parse((string) file_get_contents("{$this->project}/" . TIA_WORKFLOW_TARGET));

    expect(array_keys($workflow))->toBe(['env', 'name', 'on', 'jobs'])
        ->and(data_get($workflow, 'env.COMPOSER_AUTH'))
        ->toBe(<<<JSON
            {"github-oauth": {"github.com": "\${{ secrets.WORKFLOW_PAT }}"}}
            JSON)
        ->and(data_get($workflow, 'env.NOVA_USERNAME'))
        ->toBe('${{ secrets.NOVA_USERNAME }}')
        ->and(data_get($workflow, 'jobs.baseline.steps'))
        ->toBe(data_get(Yaml::parse(tiaWorkflow()), 'jobs.baseline.steps'));
});

it('shows the env block and its marker in every document that covers it', function (
    string $document,
): void {
    $source = shippedSource($document);

    expect($source)->toContain(markedFirst(TIA_PROJECT_ENV, "\n"));
})->with(['README.md', 'resources/boost/guidelines/06-pest-tia.md']);

it('names every secret literally', function (string $yaml): void {
    preg_match_all('/\$\{\{.*?\}\}/', $yaml, $expressions);
    $expressionText = implode("\n", (array) data_get($expressions, 0));

    expect($expressionText)->toContain('secrets.')
        ->and(preg_match('/\bsecrets\b(?!\.\w)/', $expressionText))
        ->toBe(0);
    expect($yaml)->not
        ->toContain('toJSON(secrets');
})->with([
    'the shipped workflow' => [tiaWorkflow()],
    'the env block the documents show' => [TIA_PROJECT_ENV],
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
            "secrets: \${{ secrets.TIA_BASELINE_SECRETS || '{}' }}",
        );
})->with([
    'before Composer' => ['Set up the project before Composer', BEFORE_INSTALL_HOOK],
    'after Composer' => ['Set up the project after Composer', AFTER_INSTALL_HOOK],
]);

it('hands the hooks no secret but TIA_BASELINE_SECRETS', function (): void {
    preg_match_all('/\$\{\{.*?\}\}/', tiaWorkflow(), $expressions);
    $expressionText = implode("\n", (array) data_get($expressions, 0));
    preg_match_all('/\bsecrets\b(\.\w+)?/', $expressionText, $references);

    expect(array_values(array_unique((array) data_get($references, 0))))
        ->toBe(['secrets.TIA_BASELINE_SECRETS']);
});

it('hands the mask step the secret through env alone', function (): void {
    expect(tiaWorkflowStepLines('Mask the hook secrets'))
        ->toContain('TIA_BASELINE_SECRETS: ${{ secrets.TIA_BASELINE_SECRETS }}');
    expect(tiaWorkflowStepScript('Mask the hook secrets'))->not
        ->toContain('${{');
});

it('masks every line of every value in the secret object', function (
    string $secret,
    string $expected,
): void {
    $result = runTiaMaskStep($secret);

    expect($result->exitCode())->toBe(0, $result->output());
    expect($result->output())->toBe($expected);
})->with([
    'flat values' => [
        (string) json_encode(['WORKFLOW_PAT' => 'ghp_abc', 'PIN' => SECRET_PIN]),
        "::add-mask::ghp_abc\n::add-mask::4821\n",
    ],
    'nested values' => [
        (string) json_encode(['aws' => ['key' => 'AKIA1', 'ids' => ['one', 'two']]]),
        "::add-mask::AKIA1\n::add-mask::one\n::add-mask::two\n",
    ],
    'a multi-line value, line by line' => [
        (string) json_encode(['KEY' => "-----BEGIN-----\r\nbody\n\n   \n-----END-----\n"]),
        "::add-mask::-----BEGIN-----\n::add-mask::body\n::add-mask::-----END-----\n",
    ],
    'percent and carriage return escaped' => [
        (string) json_encode(['KEY' => "50%off\rnow"]),
        "::add-mask::50%25off%0Dnow\n",
    ],
    'no keys, booleans or nulls' => [(string) json_encode(['SECRET' => true, 'OTHER' => null]), ''],
]);

it('passes without printing a secret that is not a JSON object', function (string $secret): void {
    $result = runTiaMaskStep($secret);

    expect($result->exitCode())->toBe(0);
    expect($result->output())->toBe('');
})->with([
    'undefined or empty' => [''],
    'a string' => [(string) json_encode('hunter2')],
    'an array' => [(string) json_encode(['hunter2'])],
    'invalid JSON' => ['hunter2 {'],
    'a bare word' => ['hunter2'],
]);

it('runs the generic steps in order, with each hook in its place', function (): void {
    preg_match_all('/^      - name: (.+)$/m', tiaWorkflow(), $names);

    expect(implode("\n", (array) data_get($names, 1)))->toBe(<<<TEXT
        Mask the hook secrets
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
