<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\BoostHooks;
use MikeBronner\DevelopmentSettings\Support\ManagedSection;

const EVERY_FEATURE = ['--guidelines', '--skills', '--mcp'];

const TESTBENCH_INSTALL = ['boost:install', '--no-interaction', ...EVERY_FEATURE];

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

it('writes no artisan and no .gitattributes into a package', function (): void {
    [$this->project] = makeConsumer(['app' => false, 'testbench' => true]);

    $output = publishIn($this->project);

    expect(glob("{$this->project}/{artisan,.gitattributes}", GLOB_BRACE))->toBe([]);
    expect($output)->not
        ->toMatch('/  (artisan|\.gitattributes)\b/');
    expect(boostConfigIn($this->project))
        ->toBe(['packages' => ['mike-bronner/laravel-development-settings']]);
    expect($output)->toContain('boost.json (registered with Boost)', '... done');
});

it('runs Boost in a package through the rooted Testbench, never in Composer', function (): void {
    [$this->project] = makeConsumer(['app' => false, 'testbench' => true]);

    $output = publishIn($this->project);

    expect(boostRun($this->project))->toBe([
        'APP_BASE_PATH' => realpath($this->project),
        'APP_ENV' => 'local',
        'TESTBENCH_WORKING_PATH' => realpath($this->project),
        'directories' => true,
        'arguments' => TESTBENCH_INSTALL,
    ]);
    expect($output)->toContain('... done');
    // phpcs:ignore CleanCode.Controversial.Superglobals.Found
    expect(array_key_exists('APP_BASE_PATH', $_ENV))->toBeFalse();
    expect(getenv('TESTBENCH_WORKING_PATH'))->toBeFalse();
});

it('passes every feature, whatever boost.json holds', function (string $kind, array $set): void {
    [$this->project] = makeConsumer(['app' => $kind === 'app', 'testbench' => true]);
    $boostJson = json_encode(['agents' => ['claude_code'], ...$set]);
    file_put_contents("{$this->project}/boost.json", $boostJson);

    publishIn($this->project);

    expect(data_get(boostRun($this->project), 'arguments'))->toBe(match ($kind) {
        'app' => EVERY_FEATURE,
        default => TESTBENCH_INSTALL,
    });
})->with(['app', 'package'])
    ->with([
        'no feature keys' => [[]],
        'mcp off' => [['mcp' => false]],
        'every feature off' => [['guidelines' => false, 'skills' => false, 'mcp' => false]],
    ]);

it('removes every shim it wrote, and composes without it', function (string $shim): void {
    [$this->project] = makeConsumer(['app' => false, 'testbench' => true]);
    file_put_contents("{$this->project}/artisan", $shim);

    $output = publishIn($this->project);

    expect(file_exists("{$this->project}/artisan"))->toBeFalse();
    expect($output)->toMatch('/-  artisan /');
    expect(data_get(boostRun($this->project), 'APP_BASE_PATH'))->toBe(realpath($this->project));
})->with([
    'the last shim' => [shimSource()],
    'the shim from 0.3.4' => [previouslyShippedShim()],
    'the 0.3.4 shim, its marker comment stripped' => [strippedShim()],
]);

it('keeps an edited shim and lists it, and composes without it', function (): void {
    [$this->project] = makeConsumer(['app' => false, 'testbench' => true]);
    file_put_contents("{$this->project}/artisan", shimSource() . "// Mine.\n");

    $output = publishIn($this->project);

    expect(file_get_contents("{$this->project}/artisan"))->toBe(shimSource() . "// Mine.\n");
    expect($output)->toContain('artisan (removed upstream, kept — locally modified)');
    expect(data_get(boostRun($this->project), 'APP_BASE_PATH'))->toBe(realpath($this->project));
});

it("removes the .gitattributes it wrote, unless the project's lines sit below it", function (
    string $below,
    array $kept,
    string $line,
): void {
    [$this->project] = makeConsumer(['app' => false, 'testbench' => true]);
    $local = retiredGitattributes() . ManagedSection::MARKER . "\n{$below}";
    file_put_contents("{$this->project}/.gitattributes", $local);

    $output = publishIn($this->project);

    $remaining = collect(glob("{$this->project}/.gitattributes"))
        ->map(basename(...))
        ->all();

    expect($remaining)->toBe($kept);
    expect($output)->toMatch($line);
})->with([
    'nothing below the marker' => ['', [], '/-  \.gitattributes /'],
    "the project's lines below the marker" => [
        "/tests export-ignore\n",
        ['.gitattributes'],
        '/\.gitattributes \(removed upstream, kept — locally modified\)/',
    ],
]);

it("keeps a package's own unmarked .gitattributes, and lists it", function (): void {
    [$this->project] = makeConsumer(['app' => false, 'testbench' => true]);
    file_put_contents("{$this->project}/.gitattributes", "/tests export-ignore\n");

    $output = publishIn($this->project);

    expect(file_get_contents("{$this->project}/.gitattributes"))->toBe("/tests export-ignore\n");
    expect($output)->toContain('.gitattributes (removed upstream, kept — locally modified)');
});

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

it('names the rooted Testbench install as the next step when a package composes nothing', function (
    string $run,
    array $messages,
): void {
    [$this->project] = makeConsumer([
        'app' => false,
        'testbench' => true,
        'boost' => COMPOSES_NOTHING,
        'voice' => SILENT,
    ]);

    expect(publishIn($this->project, $run))->toContain(...$messages);
})->with([
    'captured' => [
        NON_INTERACTIVE,
        [
            "Run \"" . BoostHooks::TESTBENCH_INSTALL_COMMAND . "\" once to choose them.",
            "Run \"" . BoostHooks::TESTBENCH_INSTALL_COMMAND . "\" and choose your agents.",
        ],
    ],
    'attached' => [
        INTERACTIVE_ON_A_TERMINAL,
        ["Its output is above. Run \"" . BoostHooks::TESTBENCH_INSTALL_COMMAND . "\" and choose"],
    ],
]);

it('names the rooted Testbench install as the next step when a package fails', function (): void {
    [$this->project] = makeConsumer([
        'app' => false,
        'testbench' => true,
        'boost' => EXITS_WITH_ERROR,
    ]);

    expect(publishIn($this->project))
        ->toContain("Run \"" . BoostHooks::TESTBENCH_INSTALL_COMMAND . "\" to see why.");
});
