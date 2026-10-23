<?php

declare(strict_types=1);

use Composer\IO\BufferIO;
use MikeBronner\DevelopmentSettings\Plugin\RootedMcp;
use MikeBronner\DevelopmentSettings\Support\BoostHooks;
use MikeBronner\DevelopmentSettings\Support\ProjectKind;
use MikeBronner\DevelopmentSettings\Support\RootedTestbench;

beforeEach(function (): void {
    $this->project = makeTempDir('devset-rooted-mcp-plugin-');
    seedFiles($this->project, [ProjectKind::TESTBENCH => "<?php\n"]);
    $this->output = new BufferIO;
    $this->settle = function (?int $startedAt, string $check = 'exit(0);'): string {
        $hooks = new BoostHooks(['testbench_mcp_check_command' => phpCommand(
                "file_put_contents('check.ran', getcwd()); {$check}",
            )]);
        (new RootedMcp($this->output, $this->project, $hooks))->settle($startedAt);

        return $this->output
            ->getOutput();
    };
    $this->read = fn (string $path): string => (string) file_get_contents(
            "{$this->project}/{$path}",
        );
});

afterEach(function (): void {
    removeTempDir($this->project);
});

it('roots the MCP configs Boost wrote, says so, and checks tool calls', function (): void {
    seedFiles($this->project, [
        '.mcp.json' => BOOST_MCP_JSON,
        '.cursor/mcp.json' => BOOST_MCP_JSON,
    ]);

    $output = ($this->settle)(time());

    expect(($this->read)('.mcp.json'))->toBe(rootedMcpJson())
        ->and(($this->read)('.cursor/mcp.json'))
        ->toBe(rootedMcpJson())
        ->and(($this->read)('check.ran'))
        ->toBe(realpath($this->project));
    expect($output)->toContain(
            'Pointed the laravel-boost MCP server in .cursor/mcp.json, .mcp.json at '
                . RootedTestbench::SCRIPT . ': this repository has no artisan.',
            'Checking Boost MCP tool calls... done',
        );
});

it('says nothing about configs when Boost wrote none, and still checks', function (): void {
    $output = ($this->settle)(time());

    expect($output)->not
        ->toContain('Pointed')
        ->and($output)
        ->toContain('Checking Boost MCP tool calls... done');
});

it('names only the configs it changed', function (): void {
    seedFiles($this->project, [
        '.mcp.json' => rootedMcpJson(),
        '.cursor/mcp.json' => BOOST_MCP_JSON,
    ]);

    $output = ($this->settle)(time());

    expect($output)->toContain(
            'Pointed the laravel-boost MCP server in .cursor/mcp.json at',
            'Checking Boost MCP tool calls... done',
        )
        ->and($output)
        ->not
        ->toContain('.mcp.json,', ' .mcp.json');
});

it('says nothing about a config that was already rooted', function (): void {
    seedFiles($this->project, ['.mcp.json' => rootedMcpJson()]);

    expect(($this->settle)(time()))->not
        ->toContain('Pointed');
});

it('reports a config it could not root, with the cause, and carries on', function (): void {
    seedFiles($this->project, [
        '.mcp.json' => BOOST_MCP_JSON,
        '.cursor/mcp.json' => BOOST_MCP_JSON,
    ]);
    chmod("{$this->project}/.mcp.json", MODE_READ_ONLY);
    $testbench = BoostHooks::TESTBENCH;

    $output = ($this->settle)(time());

    expect($output)->toContain(
            ".mcp.json: Could not write {$this->project}/.mcp.json",
            'so the laravel-boost MCP server it configures does not start.',
            "Point it at \"{$testbench} boost:mcp\" yourself, or run the Composer command again.",
            'Pointed the laravel-boost MCP server in .cursor/mcp.json at',
            'Checking Boost MCP tool calls... done',
        );
});

it('names a directory it could not open, and roots the rest', function (): void {
    seedFiles($this->project, ['.mcp.json' => BOOST_MCP_JSON, 'docker/data/x' => '']);
    chmod("{$this->project}/docker/data", MODE_UNREADABLE);

    $output = ($this->settle)(time());
    chmod("{$this->project}/docker/data", TEMP_DIR_PERMISSIONS);

    expect($output)->toContain(
            "docker/data: Could not open {$this->project}/docker/data: ",
            'so it was not checked for a laravel-boost MCP server.',
            'Pointed the laravel-boost MCP server in .mcp.json at',
            'Checking Boost MCP tool calls... done',
        );
});

it('reports a failed check with its cause and output, escaped', function (): void {
    $script = RootedTestbench::SCRIPT;
    $command = BoostHooks::MCP_CHECK_COMMAND;
    $check = <<<PHP
        echo 'Class <error>ToolExecutor</error> is final'; exit(1);
        PHP;

    $output = ($this->settle)(time(), $check);

    expect($output)->toContain(
            'Checking Boost MCP tool calls... failed',
            'Boost MCP tool calls in this repository would run through artisan,',
            'which does not exist.',
            "This package's service provider replaces Boost's tool executor with one",
            "that runs {$script} instead,",
            'and either the provider did not load or Boost no longer matches what it replaces.',
            "Run \"{$command}\" to see why.",
            <<<TEXT
                │ Class <error>ToolExecutor</error> is final
                TEXT,
        );
});

it('does nothing when Boost did not run, or in an app', function (
    ?int $startedAt,
    array $files,
): void {
    seedFiles($this->project, ['.mcp.json' => BOOST_MCP_JSON, ...$files]);

    $output = ($this->settle)($startedAt);

    expect($output)->toBe('')
        ->and(($this->read)('.mcp.json'))
        ->toBe(BOOST_MCP_JSON)
        ->and(file_exists("{$this->project}/check.ran"))
        ->toBeFalse();
})->with([
    'Boost did not run' => [null, []],
    'an app' => [0, ['artisan' => "#!/usr/bin/env php\n"]],
]);
