<?php

declare(strict_types=1);

use Composer\Composer;
use Composer\IO\BufferIO;
use MikeBronner\DevelopmentSettings\ComposerPlugin;
use MikeBronner\DevelopmentSettings\Support\InstalledPackage;

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

    expect(publishIn($this->project))->toContain('Developer Settings', '... done');
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
