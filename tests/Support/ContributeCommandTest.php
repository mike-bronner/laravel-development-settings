<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\ContributeCommand;
use MikeBronner\DevelopmentSettings\Support\ContributionDetector;
use MikeBronner\DevelopmentSettings\Support\PackageConfig;
use MikeBronner\DevelopmentSettings\Tests\Fixtures\RecordingProcess;

beforeEach(function (): void {
    $this->package = makeTempDir();
    $this->output = fopen('php://memory', 'w+b');
    $this->errors = fopen('php://memory', 'w+b');
    seedFiles($this->package, [
        PackageConfig::FILE => "<?php return ['capture' => ['resources/boost'], 'paths' => []];",
        ContributionDetector::MANIFEST_FILE => json_encode(['resources/boost/a.md' => [md5('a')]]),
        'resources/boost/a.md' => 'a',
    ]);
    $this->run = fn (array $exitCodes): int => (new ContributeCommand(
        '/work/app',
        $this->package,
        $this->output,
        $this->errors,
        new RecordingProcess(exitCodes: $exitCodes),
    ))->run();
    $this->streams = fn (): array => [
        (string) stream_get_contents($this->output, offset: 0),
        (string) stream_get_contents($this->errors, offset: 0),
    ];
});

afterEach(function (): void {
    removeTempDir($this->package);
});

it('says there is nothing to contribute when no source was edited', function (): void {
    expect(($this->run)([]))->toBe(0)
        ->and(($this->streams)())
        ->toBe(["No local development-settings edits to contribute.\n", '']);
});

it('names the edited sources and opens the pull request', function (): void {
    file_put_contents("{$this->package}/resources/boost/a.md", 'edited');

    $status = ($this->run)(['diff --cached --quiet' => 1]);
    [$output, $errors] = ($this->streams)();

    expect([$status, $errors])->toBe([0, ''])
        ->and($output)
        ->toMatch(
            '/\AContributing 1 edited file\(s\) to mike-bronner\/laravel-development-settings:\n'
                . '  - resources\/boost\/a\.md\n'
                . 'Opened a contribution PR from branch contribute\/app-\d{14}\.\n\z/',
        );
});

it('reports a failure on the error stream, with its exit status', function (): void {
    file_put_contents("{$this->package}/resources/boost/a.md", 'edited');

    $status = ($this->run)(['git clone' => 1]);
    [, $errors] = ($this->streams)();

    expect([$status, $errors])
        ->toBe([1, "Failed to clone mike-bronner/laravel-development-settings.\n"]);
});
