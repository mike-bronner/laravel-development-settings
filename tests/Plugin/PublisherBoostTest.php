<?php

declare(strict_types=1);

const STALE_BY = 60;

const EXITED = 'Laravel Boost exited with an error';

afterEach(function (): void {
    removeTempDir($this->project);
});

it('fails loudly when Boost exits cleanly but composes nothing', function (): void {
    [$this->project] = makeConsumer(['boost' => COMPOSES_NOTHING]);

    $output = publishIn($this->project);

    expect(boostRan($this->project))->toBeTrue();
    expect($output)->toContain(
            'names no agents',
            'Composing Laravel Boost guidelines and skills... failed',
            'composed no agent file',
            '│ Boost stand-in found no agent',
        );
    expect($output)->not
        ->toContain('done');
});

it('does not count a composed block that was on disk before the run', function (): void {
    [$this->project] = makeConsumer(['boost' => COMPOSES_NOTHING]);
    file_put_contents("{$this->project}/CLAUDE.md", agentBlock());
    touch("{$this->project}/CLAUDE.md", time() - STALE_BY);

    $output = publishIn($this->project);

    expect([str_contains($output, 'composed no agent file'), str_contains($output, 'done')])
        ->toBe([true, false]);
});

it('reports a Boost error as a failure, with its escaped output', function (): void {
    [$this->project] = makeConsumer(['boost' => EXITS_WITH_ERROR]);
    $error = <<<TEXT
        │ Boost stand-in: <error>command "boost:install"</error> is not defined
        TEXT;

    $output = publishIn($this->project);

    expect(boostRan($this->project))->toBeTrue();
    expect($output)->toContain('... failed', EXITED, $error);
    expect($output)->not
        ->toContain('composed no agent file');
});

it('runs Boost captured, with no prompts, unless it has a terminal', function (string $run): void {
    [$this->project] = makeConsumer();

    $output = publishIn($this->project, $run);

    expect(data_get(boostRun($this->project), 'arguments'))
        ->toBe(['--guidelines', '--skills', '--mcp']);
    expect(boostSharedOurStdout($this->project))->toBeFalse();
    expect($output)->toContain('names no agents', 'Laravel Boost guidelines and skills... done');
    expect($output)->not
        ->toContain('Boost stand-in');
})->with([NON_INTERACTIVE_ON_A_TERMINAL, INTERACTIVE]);

it('hands Boost the terminal, with no feature flags, when it has one', function (): void {
    [$this->project] = makeConsumer(['voice' => SILENT]);

    $output = publishIn($this->project, INTERACTIVE_ON_A_TERMINAL);

    expect(data_get(boostRun($this->project), 'arguments'))->toBe(['attached']);
    expect(boostSharedOurStdout($this->project))->toBeTrue();
    expect($output)->not
        ->toContain('names no agents');
    expect($output)
        ->toContain("Composing Laravel Boost guidelines and skills...\n", 'Laravel Boost done');
});

it('runs the attached command through the rooted Testbench in a package', function (): void {
    [$this->project] = makeConsumer(['app' => false, 'testbench' => true, 'voice' => SILENT]);

    $output = publishIn($this->project, INTERACTIVE_ON_A_TERMINAL);

    expect(boostRun($this->project))
        ->toMatchArray(['APP_BASE_PATH' => realpath($this->project), 'directories' => true]);
    expect(data_get(boostRun($this->project), 'arguments'))->toBe(['boost:install']);
    expect(boostSharedOurStdout($this->project))->toBeTrue();
    expect($output)->toContain('Laravel Boost done');
});

it('reports an attached failure, pointing above', function (string $boost, string $message): void {
    [$this->project] = makeConsumer(['voice' => SILENT, 'boost' => $boost]);

    $output = publishIn($this->project, INTERACTIVE_ON_A_TERMINAL);

    expect(boostRan($this->project))->toBeTrue();
    expect($output)->toContain('Laravel Boost failed', $message);
    expect($output)->not
        ->toContain('names no agents', 'to see why', 'done');
})->with([
    'an error' => [EXITS_WITH_ERROR, EXITED . '. Its output is above.'],
    'nothing composed' => [
        COMPOSES_NOTHING,
        "composed no agent file. Its output is above. Run \"php artisan boost:install\""
            . ' and choose at least one agent and the AI Guidelines feature.',
    ],
]);

it('refuses to hand Boost the terminal when composing would damage a file', function (): void {
    [$this->project] = makeConsumer(['voice' => SILENT]);
    file_put_contents("{$this->project}/CLAUDE.md", armedAgentFile());

    $output = publishIn($this->project, INTERACTIVE_ON_A_TERMINAL);

    expect(boostRan($this->project))->toBeFalse();
    expect($output)->toContain('overwrite hand-written content');
    expect($output)->not
        ->toContain('Composing Laravel Boost');
});

it('shows only the one summary line when a captured Boost run succeeds', function (): void {
    [$this->project] = makeConsumer();

    $output = publishIn($this->project);

    expect($output)->toContain('... done');
    expect($output)->not
        ->toContain('Boost stand-in composed AGENTS.md', '    │ ');
});

it('never writes a CLAUDE.md, once Boost composed', function (string $run): void {
    [$this->project] = makeConsumer();

    $output = publishIn($this->project, $run);

    expect(file_exists("{$this->project}/AGENTS.md"))->toBeTrue();
    expect(file_exists("{$this->project}/CLAUDE.md"))->toBeFalse();
    expect($output)->not
        ->toContain('CLAUDE.md');
})->with([NON_INTERACTIVE, INTERACTIVE_ON_A_TERMINAL]);

it('deletes a CLAUDE.md of the project, whatever Boost did', function (
    array $options,
    array $files,
): void {
    [$this->project] = makeConsumer($options);
    seedFiles($this->project, ['CLAUDE.md' => "Ours.\n", ...$files]);

    $output = publishIn($this->project);

    expect(file_exists("{$this->project}/CLAUDE.md"))->toBeFalse();
    expect($output)->toContain('Deleted CLAUDE.md');
})->with([
    'Boost composed' => [[], []],
    'Boost composed nothing' => [['boost' => COMPOSES_NOTHING], []],
    'Boost exited with an error' => [['boost' => EXITS_WITH_ERROR], []],
    'Boost could not run' => [['app' => false], []],
    'Boost was refused' => [[], ['notes.md' => armedAgentFile()]],
]);

it('keeps a CLAUDE.md Boost composed during the run', function (
    string $run,
    int $exitCode,
): void {
    $boost = boostComposingClaude($exitCode);
    [$this->project] = makeConsumer(['command' => $boost, 'interactiveCommand' => $boost]);
    file_put_contents("{$this->project}/CLAUDE.md", "Ours.\n");
    touch("{$this->project}/CLAUDE.md", time() - STALE_BY);

    $output = publishIn($this->project, $run);

    expect(file_get_contents("{$this->project}/CLAUDE.md"))->toBe(agentBlock());
    expect($output)->not
        ->toContain('Deleted CLAUDE.md');
})->with([NON_INTERACTIVE, INTERACTIVE_ON_A_TERMINAL])
    ->with([
        'Boost succeeded' => [0],
        'Boost exited with an error' => [1],
    ]);
