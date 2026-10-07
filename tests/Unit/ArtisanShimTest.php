<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\ManagedSection;
use MikeBronner\DevelopmentSettings\Support\ProjectKind;
use MikeBronner\DevelopmentSettings\Support\SystemProcess;

/*
 * A package repository holding the shipped shim. Where a test installs one,
 * its Testbench is a stand-in that prints the rooting it was booted with.
 */

beforeEach(function (): void {
    $this->project = makeTempDir('devset-shim-');
    copy(REPOSITORY_ROOT . '/resources/project/artisan', "{$this->project}/artisan");
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
    $this->runShim = fn (string $prefix = ''): mixed => (new SystemProcess)
        ->capture($prefix . escapeshellarg(PHP_BINARY) . ' artisan boost:mcp', $this->project);
});

afterEach(function (): void {
    removeTempDir($this->project);
});

it('is told from an app artisan', function (): void {
    expect((new ProjectKind)->isApp($this->project))->toBeFalse();
});

it('boots Testbench rooted at the repository, and creates what Testbench needs', function (): void {
    ($this->installTestbench)();

    $result = ($this->runShim)();

    expect($result->exitCode())->toBe(0);
    expect(json_decode($result->output(), associative: true))->toBe([
        'APP_BASE_PATH' => realpath($this->project),
        'APP_ENV' => 'local',
        'TESTBENCH_WORKING_PATH' => realpath($this->project),
        'arguments' => ['boost:mcp'],
    ]);
    expect(glob("{$this->project}/{bootstrap/cache,storage/framework/views}", GLOB_BRACE))
        ->toBe(["{$this->project}/bootstrap/cache", "{$this->project}/storage/framework/views"]);
});

it('keeps an APP_ENV the caller set', function (): void {
    ($this->installTestbench)();

    $result = ($this->runShim)('APP_ENV=testing ');

    expect(data_get(json_decode($result->output(), associative: true), 'APP_ENV'))->toBe('testing');
});

it('fails with the next step, and creates nothing, when Testbench is missing', function (): void {
    $result = ($this->runShim)();

    expect($result->exitCode())->toBe(1);
    expect($result->output())
        ->toContain(
            'This artisan is the mike-bronner/laravel-development-settings shim. It runs'
                . ' through Orchestra Testbench, which is not installed.',
        )
        ->not
        ->toContain('Fatal');
    expect(glob("{$this->project}/{bootstrap,storage}", GLOB_BRACE))->toBe([]);
});

it('needs the shim constant it is recognized by, so it is not dead code', function (): void {
    $artisan = (string) file_get_contents("{$this->project}/artisan");
    $constant = ProjectKind::SHIM_CONSTANT;
    $declaration = "const {$constant} = 'mike-bronner/laravel-development-settings';\n";
    $withoutDeclaration = str_replace($declaration, '', $artisan, $removed);
    file_put_contents("{$this->project}/artisan", $withoutDeclaration);

    $result = ($this->runShim)();

    expect($removed)->toBe(1);
    expect($result->exitCode())->not
        ->toBe(0);
    expect($result->output())->toContain("Undefined constant \"{$constant}\"");
});

it('stays out of the dist archive under the .gitattributes the sync writes', function (): void {
    $source = REPOSITORY_ROOT . '/resources/project/gitattributes';
    $gitattributes = (string) file_get_contents($source);
    seedFiles($this->project, [
        '.gitattributes' => (new ManagedSection)
            ->compose(managed: $gitattributes, project: "/tests export-ignore\n"),
        'tests/ExampleTest.php' => "<?php\n",
        'composer.json' => "{}\n",
    ]);
    $git = 'git -c user.name=test -c user.email=test@example.com -c commit.gpgsign=false ';
    $process = new SystemProcess;
    $setUp = collect(['init -q', 'add -A', 'commit -q -m initial'])
        ->map(fn (string $command): int => $process->run($git . $command, $this->project))
        ->all();

    $archived = $process->capture("{$git}archive --format=tar HEAD | tar -t", $this->project);
    $tracked = $process->capture("{$git}ls-files", $this->project);

    expect([...$setUp, $archived->exitCode()])->toBe([0, 0, 0, 0]);
    expect(explode("\n", trim($archived->output())))
        ->toEqualCanonicalizing(['.gitattributes', 'composer.json']);
    expect($tracked->output())->toContain('artisan');
});
