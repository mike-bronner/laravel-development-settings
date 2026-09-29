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

    private const CONTAINED = <<<TEXT

        <error>Developer Settings could not finish setting up this project.</error>
        TEXT;

    private const RE_RUN = "  Run \"%s\" again to finish setup.\n";

    /**
     * The command that dispatched each event `publish()` handles, and so the
     * one to run again.
     */
    private const COMMANDS = [
        'post-install-cmd' => 'composer install',
        'post-update-cmd' => 'composer update',
    ];

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
     * The names are Composer's `ScriptEvents` constants, written out.
     *
     * @return array<string, string>
     */
    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            'pre-update-cmd' => 'captureBeforeUpdate',
            'post-install-cmd' => 'publish',
            'post-update-cmd' => 'publish',
        ];
    }

    /**
     * Publish into the project, and contain an exception or error the publish
     * throws.
     *
     * An uncaught failure here aborts the whole Composer run and skips the
     * project's own `post-install-cmd` or `post-update-cmd` scripts. A run that
     * updates this plugin can fail that way through no fault of the project:
     * Composer loads the new version of this class fresh, but a class the old
     * version already loaded stays old, so new code calls a method the old
     * class lacks. The next run starts clean. So outside CI the failure is
     * printed with its cause and the command to run again, and Composer carries
     * on. A CI run installs from the lock and upgrades nothing mid-run, so a
     * failure there is a real bug, and it still fails the run.
     *
     * The handler uses nothing of this package but this class: it runs in
     * exactly the run where any other class of the package may be the old one.
     */
    public function publish(Event $event): void
    {
        try {
            $this->publishIntoProject($event);
        } catch (Throwable $failure) {
            $this->contain($failure, $event);
        }
    }

    /**
     * Name the installed sources edited in place, before an update overwrites
     * them.
     *
     * A failure here is not contained. The update has changed nothing yet, so
     * stopping it loses nothing, while carrying on would overwrite the very
     * edits this hook exists to name.
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
     * Inside this package's own repository there is nothing to publish;
     * anywhere else a missing package is an error.
     */
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

    /**
     * Under CI, rethrow: any non-empty `CI` counts, `false` and `0` included,
     * and an empty one counts as unset. Anywhere else, print the cause and the
     * command to run again, with the trace at `-v`, as Composer prints its own.
     */
    private function contain(Throwable $failure, Event $event): void
    {
        $isCi = ! in_array(getenv('CI'), [false, ''], strict: true);
        $command = self::COMMANDS[$event->getName()];

        match ($isCi) {
            true => throw $failure,
            false => $this->reportContained($failure, $event->getIO(), $command),
        };
    }

    /**
     * The cause and the trace are written raw: a tag in them is printed, not
     * read as a style.
     */
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
