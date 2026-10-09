<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\BoostHooks;

const STALE_CACHE = "<?php return ['stale' => true];\n";

afterEach(function (): void {
    removeTempDir($this->project);
});

it('rebuilds a package\'s discovery cache through Testbench before Boost runs', function (
    string $run,
): void {
    [$this->project] = makeConsumer(['app' => false, 'testbench' => true, 'voice' => SILENT]);
    seedFiles($this->project, ['bootstrap/cache/packages.php' => STALE_CACHE]);

    $output = publishIn($this->project, $run);

    expect(discoveryRun($this->project))->toBe([
        'arguments' => ['package:discover'],
        'APP_BASE_PATH' => realpath($this->project),
        'boost ran' => false,
    ]);
    expect(boostRan($this->project))->toBeTrue();
    expect($output)->toContain('Rebuilding the package discovery cache... done');
})->with([NON_INTERACTIVE, INTERACTIVE_ON_A_TERMINAL]);

it('rebuilds the cache in a package that still holds a shim', function (
    string $artisan,
    string $line,
): void {
    [$this->project] = makeConsumer(['app' => false, 'testbench' => true, 'voice' => SILENT]);
    seedFiles($this->project, [
        'artisan' => $artisan,
        'bootstrap/cache/packages.php' => STALE_CACHE,
    ]);

    $output = publishIn($this->project);

    expect(discoveryRun($this->project))->toMatchArray([
        'arguments' => ['package:discover'],
        'APP_BASE_PATH' => realpath($this->project),
    ]);
    expect($output)->toContain('Rebuilding the package discovery cache... done')
        ->toMatch($line);
})->with([
    'the 0.3.4 shim, its marker comment stripped' => [strippedShim(), '/-  artisan /'],
    'the shim with a line added' => [
        shimSource() . "// Mine.\n",
        '/artisan \(removed upstream, kept — locally modified\)/',
    ],
]);

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
            "Package discovery exited with an error. Run \"" . BoostHooks::DISCOVER_COMMAND,
            "\" to see why: a service provider installed since the cache was written may not"
                . ' load in Boost.',
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
