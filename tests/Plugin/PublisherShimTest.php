<?php

declare(strict_types=1);

/*
 * A publish into an app, which keeps its own artisan, and into a package
 * repository, which receives the artisan shim and the managed .gitattributes
 * when it has Testbench to boot.
 */

const EVERY_FEATURE = ['--guidelines', '--skills', '--mcp'];

const APP_ARTISAN = "#!/usr/bin/env php\n";

afterEach(function (): void {
    removeTempDir($this->project);
});

it('composes an app through its own artisan, and gives it no package file', function (): void {
    [$this->project] = makeConsumer(['testbench' => true]);
    file_put_contents("{$this->project}/.gitattributes", "/.github export-ignore\n");

    $output = publishIn($this->project);

    expect(boostRun($this->project))
        ->toMatchArray(['arguments' => EVERY_FEATURE, 'APP_BASE_PATH' => null]);
    expect(file_get_contents("{$this->project}/artisan"))->toBe(APP_ARTISAN);
    expect(file_get_contents("{$this->project}/.gitattributes"))->toBe("/.github export-ignore\n");
    expect($output)->not
        ->toMatch('/  (artisan|\.gitattributes)\b/');
    expect(glob("{$this->project}/{bootstrap,storage}", GLOB_BRACE))->toBe([]);
});

it("never reports or offers to delete an app's own artisan", function (): void {
    [$this->project] = makeConsumer([
        'testbench' => true,
        'packageManifest' => [
            ...shippedPackageManifest(),
            'artisan' => [md5(APP_ARTISAN)],
            'retired.txt' => [md5("x\n")],
        ],
    ]);

    $output = publishIn($this->project);

    expect(file_get_contents("{$this->project}/artisan"))->toBe(APP_ARTISAN);
    expect($output)->not
        ->toMatch('/  artisan\b/');
    expect($output)->toContain('0 removed');
});

it('writes the shim and the managed .gitattributes into a package', function (): void {
    [$this->project, $package] = makeConsumer(['app' => false, 'testbench' => true]);
    $gitattributes = (string) file_get_contents("{$package}/resources/project/gitattributes");

    $output = publishIn($this->project);

    expect(file_get_contents("{$this->project}/artisan"))->toBe(shimSource());
    expect(file_get_contents("{$this->project}/.gitattributes"))->toBe(marked($gitattributes));
    expect($output)->toMatch('/\+  artisan .*\n.*\+  \.gitattributes /');
    expect(boostConfigIn($this->project))
        ->toBe(['packages' => ['mike-bronner/laravel-development-settings']]);
    expect(data_get(boostRun($this->project), 'arguments'))->toBe(EVERY_FEATURE);
    expect($output)->toContain('boost.json (registered with Boost)', '... done');
});

it('runs Boost in a package through the shim, rooted there, never in Composer', function (): void {
    [$this->project] = makeConsumer([
        'app' => false,
        'testbench' => true,
        'command' => escapeshellarg(PHP_BINARY) . ' artisan boost:install --no-interaction',
    ]);

    $output = publishIn($this->project);

    expect(boostRun($this->project))->toBe([
        'APP_BASE_PATH' => realpath($this->project),
        'APP_ENV' => 'local',
        'TESTBENCH_WORKING_PATH' => realpath($this->project),
        'directories' => true,
        'arguments' => ['boost:install', '--no-interaction', ...EVERY_FEATURE],
    ]);
    expect($output)->toContain('... done');
    // phpcs:ignore CleanCode.Controversial.Superglobals.Found -- Testbench reads APP_BASE_PATH only from $_ENV.
    expect(array_key_exists('APP_BASE_PATH', $_ENV))->toBeFalse();
    expect(getenv('TESTBENCH_WORKING_PATH'))->toBeFalse();
});

it('passes every feature, whatever boost.json holds', function (string $kind, array $set): void {
    [$this->project] = makeConsumer(['app' => $kind === 'app', 'testbench' => true]);
    $boostJson = json_encode(['agents' => ['claude_code'], ...$set]);
    file_put_contents("{$this->project}/boost.json", $boostJson);

    publishIn($this->project);

    expect(data_get(boostRun($this->project), 'arguments'))->toBe(EVERY_FEATURE);
})->with(['app', 'package'])
    ->with([
        'no feature keys' => [[]],
        'mcp off' => [['mcp' => false]],
        'every feature off' => [['guidelines' => false, 'skills' => false, 'mcp' => false]],
    ]);

it('updates a shim it shipped before, and keeps an edited one', function (
    string $local,
    string $line,
    string $kept,
): void {
    [$this->project] = makeConsumer([
        'app' => false,
        'testbench' => true,
        'packageManifest' => [
            ...shippedPackageManifest(),
            'artisan' => [md5(shimSource() . "// Older.\n")],
        ],
    ]);
    file_put_contents("{$this->project}/artisan", shimSource() . $local);

    $output = publishIn($this->project);

    expect(file_get_contents("{$this->project}/artisan"))->toBe(shimSource() . $kept);
    expect($output)->toMatch($line);
    expect(boostRan($this->project))->toBeTrue();
})->with([
    'an older shipped shim' => ["// Older.\n", '/↻  artisan /', ''],
    'an edited shim' => ["// Mine.\n", '/artisan \(locally modified\)/', "// Mine.\n"],
]);

it('neither composes nor registers where Testbench is missing, and says so', function (
    array $files,
): void {
    [$this->project] = makeConsumer(['app' => false]);
    seedFiles($this->project, $files);

    $output = publishIn($this->project);

    expect(glob("{$this->project}/{boost.ran,boost.json,.gitattributes}", GLOB_BRACE))->toBe([]);
    expect($output)->not
        ->toContain('registered with Boost', 'Composing Laravel Boost');
    expect($output)->toContain(
            'This repository has no artisan of its own and no vendor/bin/testbench, so Laravel'
                . ' Boost was not run. This package does not install orchestra/testbench: a'
                . " package repository requires it itself. Run \"composer require --dev"
                . " orchestra/testbench\", or \"composer install\" if composer.json already"
                . ' requires it.',
        );
})->with([
    'a package with no shim' => [[]],
    'a shim whose Testbench is gone' => [['artisan' => shimSource()]],
]);

it("only warns about a package's own unmarked .gitattributes", function (): void {
    [$this->project] = makeConsumer(['app' => false, 'testbench' => true]);
    file_put_contents("{$this->project}/.gitattributes", "/tests export-ignore\n");

    $output = publishIn($this->project);

    expect(file_get_contents("{$this->project}/.gitattributes"))->toBe("/tests export-ignore\n");
    expect(file_get_contents("{$this->project}/artisan"))->toBe(shimSource());
    expect($output)->toContain('.gitattributes (locally modified, no sync marker)');
});

it('names the artisan install as the next step when a package composes nothing', function (): void {
    [$this->project] = makeConsumer([
        'app' => false,
        'testbench' => true,
        'boost' => COMPOSES_NOTHING,
    ]);

    expect(publishIn($this->project))->toContain(
            "Run \"php artisan boost:install\" once to choose them.",
            "Run \"php artisan boost:install\" and choose your agents.",
        );
});
