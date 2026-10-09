<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use MikeBronner\DevelopmentSettings\Plugin\ContributionPrompt;
use MikeBronner\DevelopmentSettings\Plugin\Publisher;
use MikeBronner\DevelopmentSettings\Support\InstalledPackage;
use MikeBronner\DevelopmentSettings\Support\SystemTerminal;
use MikeBronner\DevelopmentSettings\Support\Terminal;
use Override;
use Throwable;

final class ComposerPlugin implements EventSubscriberInterface, PluginInterface
{
    private const NOT_FOUND = <<<TEXT
        <error>Could not locate the %s package directory</error>
        TEXT;

    private const CONTAINED = <<<TEXT

        <error>Developer Settings could not finish setting up this project.</error>
        TEXT;

    private const RE_RUN = "  Run \"%s\" again to finish setup.\n";

    private const COMMANDS = [
        'post-install-cmd' => 'composer install',
        'post-update-cmd' => 'composer update',
    ];

    public function __construct(private Terminal $terminal = new SystemTerminal)
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

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            'pre-update-cmd' => 'captureBeforeUpdate',
            'post-install-cmd' => 'publish',
            'post-update-cmd' => 'publish',
        ];
    }

    public function publish(Event $event): void
    {
        try {
            $this->publishIntoProject($event);
        } catch (Throwable $failure) {
            $this->contain($failure, $event);
        }
    }

    public function captureBeforeUpdate(Event $event): void
    {
        $projectDir = (string) getcwd();
        $packageDir = (new InstalledPackage($projectDir))->directory();

        match ($packageDir) {
            null => null,
            default => (new ContributionPrompt($event->getIO(), $projectDir, $packageDir))->offer(),
        };
    }

    private function publishIntoProject(Event $event): void
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

    private function contain(Throwable $failure, Event $event): void
    {
        $isCi = ! in_array(getenv('CI'), [false, ''], strict: true);
        $command = self::COMMANDS[$event->getName()];

        match ($isCi) {
            true => throw $failure,
            false => $this->reportContained($failure, $event->getIO(), $command),
        };
    }

    private function reportContained(
        Throwable $failure,
        IOInterface $inputOutput,
        string $command,
    ): void {
        $cause = sprintf(
                '  %s: %s in %s:%d',
                $failure::class,
                $failure->getMessage(),
                $failure->getFile(),
                $failure->getLine(),
            );

        $inputOutput->writeError(self::CONTAINED);
        $inputOutput->writeErrorRaw($cause);
        $inputOutput->writeError(sprintf(self::RE_RUN, $command));
        $inputOutput->writeErrorRaw($failure->getTraceAsString(), verbosity: IOInterface::VERBOSE);
    }

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
