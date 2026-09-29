<?php

declare(strict_types=1);

use Composer\Composer;
use Composer\IO\BufferIO;
use Composer\Script\ScriptEvents;
use MikeBronner\DevelopmentSettings\ComposerPlugin;
use MikeBronner\DevelopmentSettings\Support\InstalledPackage;
use MikeBronner\DevelopmentSettings\Support\PackageConfig;
use Symfony\Component\Console\Output\OutputInterface;

const UNDEFINED_METHOD = 'return (new stdClass())->passthru();';

const RE_RUN = "Run \"composer update\" again to finish setup.";

const RE_RUN_INSTALL = "Run \"composer install\" again to finish setup.";

afterEach(function (): void {
    removeTempDir($this->project);
});

it('captures before an update and publishes after install and update', function (): void {
    $this->project = makeTempDir();

    expect(ComposerPlugin::getSubscribedEvents())->toBe([
        'pre-update-cmd' => 'captureBeforeUpdate',
        'post-install-cmd' => 'publish',
        'post-update-cmd' => 'publish',
    ]);
});

it('does nothing when Composer activates, deactivates or uninstalls it', function (): void {
    $this->project = makeTempDir();
    $plugin = new ComposerPlugin();
    $output = new BufferIO();

    $plugin->activate(new Composer(), $output);
    $plugin->deactivate(new Composer(), $output);
    $plugin->uninstall(new Composer(), $output);

    expect($output->getOutput())->toBe('');
});

it('publishes the package it finds in the project\'s vendor directory', function (): void {
    [$this->project] = makeConsumer();

    expect(publishIn($this->project))
        ->toContain('Developer Settings', '... done')
        ->not
        ->toContain('could not finish');
});

it('says so when the package is missing from vendor', function (): void {
    $this->project = makeTempDir();

    expect(publishIn($this->project))
        ->toContain('Could not locate the ' . InstalledPackage::NAME . ' package directory');
});

it('publishes nothing into its own repository', function (): void {
    $this->project = makeTempDir();
    $composerJson = json_encode(['name' => InstalledPackage::NAME]);
    file_put_contents("{$this->project}/composer.json", $composerJson);

    expect(publishIn($this->project))->toBe('');
});

it('names edited sources before an update, and nothing without the package', function (): void {
    [$this->project, $package] = makeConsumer([
        'sources' => ['resources/boost/guidelines/01-identity.md' => "Edited\n"],
    ]);

    expect(publishIn($this->project, hook: 'captureBeforeUpdate'))->toContain('01-identity.md');

    removeTempDir($package);

    expect(publishIn($this->project, hook: 'captureBeforeUpdate'))->toBe('');
});

it('contains a publish failure outside CI, and the later scripts run', function (): void {
    [$this->project, $package] = brokenConsumer(UNDEFINED_METHOD);
    $configFile = realpath("{$package}/" . PackageConfig::FILE);

    $output = withCi(null, fn (): string => dispatchIn($this->project));

    expect($output)
        ->toContain(
            'Developer Settings could not finish setting up this project.',
            "Error: Call to undefined method stdClass::passthru() in {$configFile}:1",
            RE_RUN,
        )
        ->not
        ->toContain(RE_RUN_INSTALL)
        ->and("{$this->project}/later.ran")
        ->toBeFile();
});

it('fails the run under any set CI, and runs no later script', function (string $ciValue): void {
    [$this->project] = brokenConsumer(UNDEFINED_METHOD);
    $dispatchUnderCi = fn (): string => dispatchIn($this->project);
    $dispatch = fn (): string => withCi($ciValue, $dispatchUnderCi);

    expect($dispatch)
        ->toThrow(Error::class, 'Call to undefined method stdClass::passthru()')
        ->and("{$this->project}/later.ran")
        ->not
        ->toBeFile();
})->with(['true', 'false']);

it('names composer install after an install, and never composer update', function (): void {
    [$this->project] = brokenConsumer(UNDEFINED_METHOD);
    $dispatch = fn (): string => dispatchIn($this->project, ScriptEvents::POST_INSTALL_CMD);

    expect(withCi(null, $dispatch))
        ->toContain(RE_RUN_INSTALL)
        ->not
        ->toContain(RE_RUN)
        ->and("{$this->project}/later.ran")
        ->toBeFile();
});

it('reads an empty CI as unset', function (): void {
    [$this->project] = brokenConsumer(UNDEFINED_METHOD);

    expect(withCi('', fn (): string => dispatchIn($this->project)))
        ->toContain(RE_RUN);
});

it('prints a tag in the cause as text, not as a style', function (): void {
    $statement = <<<PHP
        throw new RuntimeException('<comment>tagged</comment>');
        PHP;
    $cause = <<<TEXT
        RuntimeException: <comment>tagged</comment> in
        TEXT;
    [$this->project] = brokenConsumer($statement);

    expect(withCi(null, fn (): string => dispatchIn($this->project)))
        ->toContain($cause);
});

it('prints the trace of a contained failure at -v only', function (): void {
    [$this->project] = brokenConsumer(UNDEFINED_METHOD);
    $trace = 'ComposerPlugin->publish(';
    $verbose = OutputInterface::VERBOSITY_VERBOSE;

    expect(withCi(null, fn (): string => dispatchIn($this->project)))
        ->not
        ->toContain($trace)
        ->and(withCi(null, fn (): string => dispatchIn($this->project, verbosity: $verbose)))
        ->toContain($trace);
});

it('contains nothing when the publish reports its own failure', function (): void {
    [$this->project] = makeConsumer(['boost' => EXITS_WITH_ERROR]);

    expect(withCi('true', fn (): string => dispatchIn($this->project)))
        ->toContain('Developer Settings', '... failed')
        ->not
        ->toContain('could not finish');
});
