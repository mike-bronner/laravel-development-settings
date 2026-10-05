<?php

declare(strict_types=1);

/*
 * A package repository's discovery cache is rebuilt through the shim before
 * Boost runs, so a provider installed since it was first written loads. An app
 * rebuilds its own in its Composer scripts.
 */

const STALE_CACHE = "<?php return ['stale' => true];\n";

afterEach(function (): void {
    removeTempDir($this->project);
});

it('rebuilds a package\'s discovery cache through the shim before Boost runs', function (
    string $run,
): void {
    [$this->project] = makeConsumer(['app' => false, 'testbench' => true, 'voice' => SILENT]);
    seedFiles($this->project, ['bootstrap/cache/packages.php' => STALE_CACHE]);

    $output = publishIn($this->project, $run);

    expect(discoveryRun($this->project))
        ->toBe(['arguments' => ['package:discover'], 'boost ran' => false]);
    expect(boostRan($this->project))->toBeTrue();
    expect($output)->toContain('Rebuilding the package discovery cache... done');
})->with([NON_INTERACTIVE, INTERACTIVE_ON_A_TERMINAL]);

it('runs no package discovery in an app', function (): void {
    [$this->project] = makeConsumer(['testbench' => true]);
    $artisan = <<<PHP
        #!/usr/bin/env php
        <?php
        \$cache = 'bootstrap/cache/packages.php';
        (\$argv[1] ?? null) === 'package:discover' && file_put_contents(\$cache, 'rebuilt');
        PHP;
    seedFiles($this->project, [
        'artisan' => $artisan,
        'bootstrap/cache/packages.php' => STALE_CACHE,
    ]);

    $output = publishIn($this->project);

    expect(file_get_contents("{$this->project}/bootstrap/cache/packages.php"))->toBe(STALE_CACHE);
    expect(boostRan($this->project))->toBeTrue();
    expect($output)->not
        ->toContain('package discovery');
});

it('reports a failed discovery with its escaped output, and still runs Boost', function (): void {
    [$this->project] = makeConsumer([
        'app' => false,
        'testbench' => true,
        'discoveryExit' => 1,
    ]);
    $error = <<<TEXT
        │ Discovery stand-in: <error>Class "Provider" not found</error>
        TEXT;

    $output = publishIn($this->project);

    expect($output)->toContain(
        'Rebuilding the package discovery cache... failed',
        "Package discovery exited with an error. Run \"php artisan package:discover\" to see why:"
            . ' a service provider installed since the cache was written may not load in Boost.',
        $error,
        'Composing Laravel Boost guidelines and skills... done',
    );
    expect(boostRan($this->project))->toBeTrue();
});

it('runs no discovery when composing would damage an agent file', function (): void {
    [$this->project] = makeConsumer(['app' => false, 'testbench' => true]);
    file_put_contents("{$this->project}/CLAUDE.md", armedAgentFile());

    $output = publishIn($this->project);

    expect(glob("{$this->project}/{bootstrap/cache/packages.php,boost.ran}", GLOB_BRACE))->toBe([]);
    expect($output)->not
        ->toContain('package discovery');
});
