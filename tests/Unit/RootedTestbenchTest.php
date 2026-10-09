<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\ProjectKind;
use MikeBronner\DevelopmentSettings\Support\SystemProcess;

beforeEach(function (): void {
    $this->project = makeTempDir('devset-rooted-');
    $this->installTestbench = fn (): mixed => seedFiles($this->project, [
        ProjectKind::TESTBENCH => <<<PHP
            <?php echo json_encode([
                'APP_BASE_PATH' => \$_ENV['APP_BASE_PATH'],
                'APP_ENV' => \$_ENV['APP_ENV'],
                'TESTBENCH_WORKING_PATH' => getenv('TESTBENCH_WORKING_PATH'),
                'arguments' => array_slice(\$argv, 1),
            ]);
            PHP,
    ]);
    $this->run = fn (string $prefix = '', string $options = ''): mixed => (new SystemProcess)
        ->capture(
            $prefix . escapeshellarg(PHP_BINARY) . " {$options}"
                . escapeshellarg(REPOSITORY_ROOT . '/bin/rooted-testbench.php') . ' boost:mcp',
            $this->project,
        );
    $this->rooting = fn (string $output): mixed => json_decode($output, associative: true);
});

afterEach(function (): void {
    removeTempDir($this->project);
});

it('boots Testbench rooted at the repository, and creates what Testbench needs', function (): void {
    ($this->installTestbench)();

    $result = ($this->run)();

    expect($result->exitCode())->toBe(0);
    expect(($this->rooting)($result->output()))->toBe([
        'APP_BASE_PATH' => realpath($this->project),
        'APP_ENV' => 'local',
        'TESTBENCH_WORKING_PATH' => realpath($this->project),
        'arguments' => ['boost:mcp'],
    ]);
    expect(glob("{$this->project}/{bootstrap/cache,storage/framework/views}", GLOB_BRACE))
        ->toBe(["{$this->project}/bootstrap/cache", "{$this->project}/storage/framework/views"]);
    expect(file_exists("{$this->project}/artisan"))->toBeFalse();
});

it('roots Testbench when PHP fills no $_ENV from the environment', function (): void {
    ($this->installTestbench)();

    $result = ($this->run)(options: '-d variables_order=GPCS ');

    expect(data_get(($this->rooting)($result->output()), 'APP_BASE_PATH'))
        ->toBe(realpath($this->project));
});

it('keeps an APP_ENV the caller set', function (): void {
    ($this->installTestbench)();

    $result = ($this->run)('APP_ENV=testing ');

    expect(data_get(($this->rooting)($result->output()), 'APP_ENV'))->toBe('testing');
});

it('fails with the next step, and creates nothing, when Testbench is missing', function (): void {
    $result = ($this->run)();

    expect($result->exitCode())->toBe(1);
    expect($result->output())
        ->toContain('Run this from a package repository with orchestra/testbench installed:')
        ->not
        ->toContain('Fatal');
    expect(glob("{$this->project}/{bootstrap,storage}", GLOB_BRACE))->toBe([]);
});
