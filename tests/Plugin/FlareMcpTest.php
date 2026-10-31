<?php

declare(strict_types=1);

use Composer\IO\BufferIO;
use MikeBronner\DevelopmentSettings\Plugin\FlareMcp;
use MikeBronner\DevelopmentSettings\Support\BoostHooks;
use MikeBronner\DevelopmentSettings\Support\ProjectKind;

const FLARE_RECORDS = "file_put_contents('flare.ran', getcwd());";

beforeEach(function (): void {
    $this->project = makeTempDir('devset-flare-mcp-plugin-');
    $this->output = new BufferIO;
    $this->settle = function (?int $startedAt, string $script = 'exit(0);'): string {
        $hooks = new BoostHooks([
            'flare_mcp_command' => phpCommand("file_put_contents('app.ran', ''); {$script}"),
            'testbench_flare_mcp_command' => phpCommand(
                    "file_put_contents('testbench.ran', ''); {$script}",
                ),
        ]);
        (new FlareMcp($this->output, $this->project, $hooks))->settle($startedAt);

        return $this->output
            ->getOutput();
    };
    $this->withFlare = fn (string $client = 'spatie/laravel-flare') => seedFiles(
            $this->project,
            ["vendor/{$client}/composer.json" => '{}'],
        );
    $this->ran = fn (string $kind): bool => file_exists("{$this->project}/{$kind}.ran");
});

afterEach(function (): void {
    removeTempDir($this->project);
});

it('adds the Flare server through artisan in an app with a Flare client', function (
    string $client,
): void {
    seedFiles($this->project, [ProjectKind::ARTISAN => "<?php\n"]);
    ($this->withFlare)($client);

    $output = ($this->settle)(time(), FLARE_RECORDS);

    expect($output)->toContain('Adding the Flare MCP server... done')
        ->and(($this->ran)('app'))
        ->toBeTrue()
        ->and(($this->ran)('testbench'))
        ->toBeFalse()
        ->and(file_get_contents("{$this->project}/flare.ran"))
        ->toBe(realpath($this->project));
})->with(['spatie/laravel-flare', 'spatie/flare-client-php']);

it('adds it through the rooted Testbench in a package repository', function (): void {
    seedFiles($this->project, [ProjectKind::TESTBENCH => "<?php\n"]);
    ($this->withFlare)();

    $output = ($this->settle)(time());

    expect($output)->toContain('Adding the Flare MCP server... done')
        ->and(($this->ran)('testbench'))
        ->toBeTrue()
        ->and(($this->ran)('app'))
        ->toBeFalse();
});

it('runs nothing, and says nothing, without a Flare client', function (): void {
    seedFiles($this->project, [ProjectKind::ARTISAN => "<?php\n"]);

    expect(($this->settle)(time()))->toBe('')
        ->and(($this->ran)('app'))
        ->toBeFalse();
});

it('runs nothing, and says nothing, when Boost did not run', function (): void {
    seedFiles($this->project, [ProjectKind::ARTISAN => "<?php\n"]);
    ($this->withFlare)();

    expect(($this->settle)(null))->toBe('')
        ->and(($this->ran)('app'))
        ->toBeFalse();
});

it('reports a failure with the command to run and its output, escaped, and returns', function (
    string $kind,
    array $files,
    string $command,
): void {
    seedFiles($this->project, $files);
    ($this->withFlare)();
    $script = <<<PHP
        echo 'Boost <error>installHttpMcp</error> is gone', PHP_EOL; exit(1);
        PHP;

    $output = ($this->settle)(time(), $script);

    expect($output)->toContain(
            'Adding the Flare MCP server... failed',
            'The Flare MCP server was not added to every agent Laravel Boost configured MCP for.',
            "Run \"{$command}\" to see why.",
            <<<TEXT
                    │ Boost <error>installHttpMcp</error> is gone
                TEXT,
        )
        ->and(($this->ran)($kind))
        ->toBeTrue();
})->with([
    'an app' => ['app', [ProjectKind::ARTISAN => "<?php\n"], BoostHooks::FLARE_MCP_COMMAND],
    'a package' => [
        'testbench',
        [ProjectKind::TESTBENCH => "<?php\n"],
        BoostHooks::TESTBENCH_FLARE_MCP_COMMAND,
    ],
]);

it('reports a PHP fatal error with its message', function (): void {
    seedFiles($this->project, [ProjectKind::ARTISAN => "<?php\n"]);
    ($this->withFlare)();

    $output = ($this->settle)(time(), 'flare_function_that_does_not_exist();');

    expect($output)->toContain(
            'Adding the Flare MCP server... failed',
            'Call to undefined function flare_function_that_does_not_exist()',
        );
});
