<?php

declare(strict_types=1);

use Composer\IO\BufferIO;
use MikeBronner\DevelopmentSettings\Plugin\BoostRun;
use MikeBronner\DevelopmentSettings\Plugin\Tally;
use MikeBronner\DevelopmentSettings\Support\BoostHooks;
use MikeBronner\DevelopmentSettings\Support\BoostRegistrar;
use MikeBronner\DevelopmentSettings\Support\GuidelineGuard;
use MikeBronner\DevelopmentSettings\Support\PhpGuideline;
use MikeBronner\DevelopmentSettings\Tests\Fixtures\DetachedTerminal;

/*
 * A command that only records having run stands in for the Boost command, so
 * the marker file answers whether composition was attempted.
 */

beforeEach(function (): void {
    $this->project = makeTempDir('devset-boost-');
    $this->output = new BufferIO();
    $marker = var_export("{$this->project}/composed.marker", true);
    $command = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg("touch({$marker});") . ' --';
    $this->boost = new BoostRun(
            $this->output,
            new DetachedTerminal(),
            new BoostHooks(['command' => $command, 'description' => 'Updating Laravel Boost...']),
            $this->project,
        );
    $this->compose = function (): string {
        $boost = $this->boost;
        $boost->run();

        return $this->output
            ->getOutput();
    };
    $this->composed = fn (): bool => file_exists("{$this->project}/composed.marker");
    file_put_contents("{$this->project}/artisan", "#!/usr/bin/env php\n");
});

afterEach(function (): void {
    removeTempDir($this->project);
});

it('composes into an agent file Boost already manages', function (): void {
    file_put_contents("{$this->project}/CLAUDE.md", managedAgentFile());

    expect(($this->compose)())->toContain('Updating Laravel Boost...');
    expect(($this->composed)())->toBeTrue();
});

it('refuses to compose over a file it would damage, and says which and why', function (): void {
    file_put_contents("{$this->project}/CLAUDE.md", armedAgentFile());

    $output = ($this->compose)();

    expect([($this->composed)(), str_contains($output, 'Updating Laravel Boost...')])
        ->toBe([false, false]);
    expect($output)->toContain(
            'CLAUDE.md',
            'overwrite hand-written content',
            GuidelineGuard::OPENING_TAG,
            'between the first tag and the next closing tag',
        );
    expect(file_get_contents("{$this->project}/CLAUDE.md"))->toBe(armedAgentFile());
});

it('tells a project with neither artisan nor Testbench why Boost did not run', function (): void {
    unlink("{$this->project}/artisan");

    expect(($this->compose)())
        ->toContain('no artisan of its own and no vendor/bin/testbench, so Laravel Boost was not');
    expect(($this->composed)())->toBeFalse();
});

it('runs Boost without first looking for it in vendor', function (): void {
    ($this->compose)();

    expect(is_dir("{$this->project}/vendor/laravel/boost"))->toBeFalse();
    expect(($this->composed)())->toBeTrue();
});

it('registers the package where Boost composes, and counts and lists it', function (): void {
    $boost = $this->boost;
    $tally = new Tally();

    $boost->register();
    $boost->count($tally);

    expect([$boost->summaryLines(), $tally->count(Tally::NEW)])
        ->toBe([[['registered', BoostRegistrar::FILE]], 1]);
});

it('neither runs nor registers over a boost.json it cannot read', function (): void {
    file_put_contents("{$this->project}/boost.json", '{not json');
    $boost = $this->boost;
    $tally = new Tally();

    $boost->register();
    $boost->count($tally);

    expect(($this->compose)())->toContain('boost.json is not valid JSON');
    expect([$boost->summaryLines(), $tally->count(Tally::SKIPPED), ($this->composed)()])
        ->toBe([[], 1, false]);
});

it('registers nothing where Boost cannot compose', function (): void {
    unlink("{$this->project}/artisan");
    $boost = $this->boost;

    $boost->register();

    expect([file_exists("{$this->project}/boost.json"), $boost->summaryLines()])->toBe([false, []]);
});

it('fails a run that composed Boost\'s PHPDoc rule, naming the exclusion', function (): void {
    file_put_contents("{$this->project}/AGENTS.md", agentBlock(PhpGuideline::DOCBLOCK_RULE));

    $output = ($this->compose)();

    expect($output)->toContain(
            'failed',
            'into AGENTS.md',
            "\"php\" to boost.guidelines.exclude",
            'package discovery is not turned off for mike-bronner/laravel-development-settings',
        );
    expect($output)->not
        ->toContain('done');
});

it('reports done when the PHPDoc rule is only outside the composed block', function (): void {
    file_put_contents("{$this->project}/CLAUDE.md", agentFile(PhpGuideline::DOCBLOCK_RULE));

    $output = ($this->compose)();

    expect($output)->toContain('done');
    expect($output)->not
        ->toContain('boost.guidelines.exclude');
});
