<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use MikeBronner\DevelopmentSettings\Plugin\ContributionPrompt;
use MikeBronner\DevelopmentSettings\Plugin\Publisher;
use MikeBronner\DevelopmentSettings\Support\InstalledPackage;
use MikeBronner\DevelopmentSettings\Support\SystemTerminal;
use MikeBronner\DevelopmentSettings\Support\Terminal;
use Override;

/**
 * The Composer plugin: before an update it names local edits to the installed
 * guideline and skill sources, and after an install or an update it publishes
 * the package into the project. Each hook finds the package in the project's
 * vendor directory, and hands the work to `ContributionPrompt` and
 * `Publisher`.
 */
final class ComposerPlugin implements EventSubscriberInterface, PluginInterface
{
    private const NOT_FOUND = <<<TEXT
        <error>Could not locate the %s package directory</error>
        TEXT;

    /**
     * Composer builds the plugin with no arguments, so the terminal is
     * detected. A test passes one, because the suite's own terminal is not the
     * one a real Composer run has.
     */
    public function __construct(private Terminal $terminal = new SystemTerminal())
    {
    }

    #[Override]
    public function activate(Composer $composer, IOInterface $inputOutput): void
    {
    }

    #[Override]
    public function deactivate(Composer $composer, IOInterface $inputOutput): void
    {
    }

    #[Override]
    public function uninstall(Composer $composer, IOInterface $inputOutput): void
    {
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::PRE_UPDATE_CMD => 'captureBeforeUpdate',
            ScriptEvents::POST_INSTALL_CMD => 'publish',
            ScriptEvents::POST_UPDATE_CMD => 'publish',
        ];
    }

    /**
     * Publish into the project. Inside this package's own repository there is
     * nothing to publish; anywhere else a missing package is an error.
     */
    public function publish(Event $event): void
    {
        $inputOutput = $event->getIO();
        $package = new InstalledPackage((string) getcwd());
        $packageDir = $package->directory();

        match (true) {
            $packageDir !== null => $this->publishFrom($package, $packageDir, $event),
            $package->isOwnRepository() => null,
            default => $inputOutput->writeError(sprintf(self::NOT_FOUND, InstalledPackage::NAME)),
        };
    }

    /**
     * Name the installed sources edited in place, before an update overwrites
     * them.
     */
    public function captureBeforeUpdate(Event $event): void
    {
        $projectDir = (string) getcwd();
        $packageDir = (new InstalledPackage($projectDir))->directory();

        match ($packageDir) {
            null => null,
            default => (new ContributionPrompt($event->getIO(), $projectDir, $packageDir))->offer(),
        };
    }

    /**
     * The project's direct requirements, dev included, come from the root
     * package Composer already loaded: lowercase, and never read from the file.
     * Composer lowercases a name written in capitals, but Boost matches the
     * file's keys exactly, so only a lowercase requirement satisfies Boost.
     */
    private function publishFrom(InstalledPackage $package, string $packageDir, Event $event): void
    {
        $project = $event->getComposer()
            ->getPackage();
        $publisher = new Publisher(
            $event->getIO(),
            $this->terminal,
            (string) getcwd(),
            $packageDir,
            array_keys([...$project->getRequires(), ...$project->getDevRequires()]),
        );
        $publisher->publish($package->config($packageDir));
    }
}
