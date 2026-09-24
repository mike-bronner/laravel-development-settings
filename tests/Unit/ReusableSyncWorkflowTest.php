<?php

declare(strict_types=1);
use MikeBronner\DevelopmentSettings\Support\ManagedSection;

/*
 * The package ships the calling workflow, so it runs the reverse sync on
 * itself. Its root .gitignore differs from the shipped one on purpose, and a
 * run here proposed the root copy over the shipped source (PR #32). The guard
 * sits on the job, directly under its key, so every step is skipped.
 */
it('skips the reverse sync when the package itself is the caller', function (): void {
    $workflow = file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/reusable-sync.yml');

    expect($workflow)->toMatch(
        "/^  sync:\n(?:    #.*\n)*    if: github\.repository != 'mike-bronner\/laravel-development-settings'\n/m",
    );
});

/*
 * Runs the workflow's own PHP step, as written in the YAML, in a project
 * beside a package checkout built from this repository's sources. It proves
 * the step requires every class it uses and writes only the managed section.
 */

/**
 * A project whose `.gitignore` carries an edit above the marker, beside a
 * package checkout.
 *
 * @return array{0: string, 1: string, 2: string} project dir, package dir, shipped stub
 */
function syncFixture(): array
{
    $root = dirname(__DIR__, 2);
    $project = makeTempDir('devset-sync-');
    $package = $project . '/_laravel-development-settings';

    mkdir($package . '/src', 0755, true);
    mkdir($package . '/config');
    mkdir($package . '/resources/project', 0755, true);
    exec('cp -R ' . escapeshellarg($root . '/src/Support') . ' ' . escapeshellarg($package . '/src/Support'));
    copy($root . '/config/development-settings.php', $package . '/config/development-settings.php');
    copy($root . '/manifest.json', $package . '/manifest.json');

    $shipped = (string) file_get_contents($root . '/resources/project/gitignore');
    copy($root . '/resources/project/gitignore', $package . '/resources/project/gitignore');
    file_put_contents($project . '/.gitignore', $shipped . "/storage/edited\n" . ManagedSection::MARKER . "\n!AGENTS.md\n");

    return [$project, $package, $shipped];
}

/**
 * @return array{0: int, 1: string} exit code, output
 */
function runSyncStep(string $project): array
{
    $workflow = (string) file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/reusable-sync.yml');

    expect(preg_match("/^          php -r '\n(.*?)\n          '\n/ms", $workflow, $match))->toBe(1);

    $command = 'cd ' . escapeshellarg($project) . ' && ' . escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($match[1]) . ' 2>&1';
    exec($command, $output, $exitCode);

    return [$exitCode, implode("\n", $output)];
}

it('copies only the managed section of the project .gitignore into the package checkout', function (): void {
    [$project, $package, $shipped] = syncFixture();

    [$exitCode, $output] = runSyncStep($project);

    expect($exitCode)->toBe(0, $output)
        ->and(file_get_contents($package . '/resources/project/gitignore'))->toBe($shipped . "/storage/edited\n")
        ->and($output)->toContain('".gitignore" -> "resources\/project\/gitignore"');

    removeTempDir($project);
});

it('fails the step when a changed file cannot be written to the package checkout', function (): void {
    [$project, $package, $shipped] = syncFixture();
    chmod($package . '/resources/project/gitignore', 0444);

    [$exitCode, $output] = runSyncStep($project);

    chmod($package . '/resources/project/gitignore', 0644);

    expect($exitCode)->toBe(1, $output)
        ->and($output)->toContain('Could not write _laravel-development-settings/resources/project/gitignore: ')
        ->and($output)->not->toContain('->')
        ->and(file_get_contents($package . '/resources/project/gitignore'))->toBe($shipped);

    removeTempDir($project);
});

/*
 * Runs the workflow's change detection, as written in the YAML, on a package
 * checkout holding changed files, and renders the PR body from its outputs.
 * File names are project input, so each one must stay inside the fence.
 */

/**
 * @return array<string, string> the step outputs, by name
 */
function runDetectStep(string $project): array
{
    $workflow = (string) file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/reusable-sync.yml');

    expect(preg_match("/^      - name: Detect changes\n.*?^        run: \|\n(.*?)\n\n      - name:/ms", $workflow, $match))->toBe(1);

    $script = (string) preg_replace('/^ {10}/m', '', $match[1]);
    $outputFile = $project . '/github-output';
    touch($outputFile);

    $command = 'cd ' . escapeshellarg($project) . ' && GITHUB_OUTPUT=' . escapeshellarg($outputFile) . ' bash -e -c ' . escapeshellarg($script) . ' 2>&1';
    exec($command, $output, $exitCode);

    expect($exitCode)->toBe(0, implode("\n", $output));

    $lines = explode("\n", (string) file_get_contents($outputFile));
    $outputs = [];

    while (($line = array_shift($lines)) !== null) {
        if (preg_match('/^(\w+)<<(\S+)$/', $line, $heredoc) === 1) {
            $value = [];

            while (($next = array_shift($lines)) !== null && $next !== $heredoc[2]) {
                $value[] = $next;
            }

            $outputs[$heredoc[1]] = implode("\n", $value);
        } elseif (preg_match('/^(\w+)=(.*)$/', $line, $pair) === 1) {
            $outputs[$pair[1]] = $pair[2];
        }
    }

    return $outputs;
}

/**
 * The PR body as the workflow writes it, with the step outputs filled in.
 *
 * @param  array<string, string>  $outputs
 */
function renderPullRequestBody(array $outputs): string
{
    $workflow = (string) file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/reusable-sync.yml');

    expect(preg_match("/^          body: \|\n(.*?)\n          commit-message:/ms", $workflow, $match))->toBe(1);

    $body = (string) preg_replace('/^ {12}/m', '', $match[1]);
    $body = (string) preg_replace_callback(
        '/\$\{\{ steps\.changes\.outputs\.(\w+) \}\}/',
        fn (array $name): string => $outputs[$name[1]] ?? '',
        $body,
    );

    return (string) preg_replace('/\$\{\{ [^}]+ \}\}/', 'owner/project', $body);
}

it('keeps every changed file name inside the code fence of the PR body', function (): void {
    $project = makeTempDir('devset-detect-');
    $package = $project . '/_laravel-development-settings';

    // pint.json is staged, then edited, so `git diff` reports it. The other
    // two are untracked.
    mkdir($package);
    file_put_contents($package . '/pint.json', 'shipped');
    exec('cd ' . escapeshellarg($package) . ' && git init -q && git add pint.json');
    file_put_contents($package . '/pint.json', 'edited');
    file_put_contents($package . '/a```b.txt', 'x');
    file_put_contents($package . '/````', 'x');

    $lines = explode("\n", renderPullRequestBody(runDetectStep($project)));
    $open = (int) array_search('**Changed files:**', $lines, strict: true) + 1;
    $fence = $lines[$open];
    $closing = '/^ {0,3}`{' . strlen($fence) . ',}[ \t]*$/';
    $length = 0;

    while (preg_match($closing, $lines[$open + 1 + $length]) !== 1) {
        $length++;
    }

    expect($fence)->toMatch('/^`{3,}$/')
        ->and(array_slice($lines, $open + 1, $length))->toBe(['````', 'a```b.txt', 'pint.json']);

    removeTempDir($project);
});
