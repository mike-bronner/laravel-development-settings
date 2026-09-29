<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Plugin;

use Composer\IO\IOInterface;
use MikeBronner\DevelopmentSettings\Support\BoostHooks;
use MikeBronner\DevelopmentSettings\Support\BoostRegistrar;
use MikeBronner\DevelopmentSettings\Support\GuidelineGuard;
use MikeBronner\DevelopmentSettings\Support\InstalledPackage;
use MikeBronner\DevelopmentSettings\Support\ProcessResult;
use MikeBronner\DevelopmentSettings\Support\ProjectKind;
use MikeBronner\DevelopmentSettings\Support\SystemProcess;
use MikeBronner\DevelopmentSettings\Support\Terminal;

/**
 * Install Laravel Boost through the project's `artisan`: an app's own, or the
 * shim a package repository receives. A captured run installs the guidelines,
 * skills and MCP entries whatever `boost.json` says. A run on the terminal
 * lets Boost's own prompts choose, and saves the choice.
 *
 * Boost composes from this package's `resources/boost` in vendor *and* from
 * the project's own `.ai`, so the run is unconditional: the package sources
 * alone can no longer tell us whether the output would change.
 *
 * Every composition passes through here, so this is where the agent files are
 * checked first. Boost overwrites hand-written content whenever a file names
 * its opening marker tag more than once, or leaves one unclosed, and this
 * package composes unattended at a moment the project's author did not pick.
 * `GuidelineGuard` carries the rule and the reasoning.
 *
 * An interactive Composer run on a terminal hands Boost the terminal, as
 * Composer does for a script, with no feature flags: with any of them, Boost
 * asks for agents but does not save the answer. Its output then goes to the
 * user, not to the plugin. Anything else, CI included, runs captured, never
 * prompts, and passes every feature explicitly, so a leftover `boost.json`
 * setting can never turn one off: Boost ignores `boost.json` once a flag is
 * given.
 */
final class BoostRun
{
    private const FEATURES = ' --guidelines --skills --mcp';

    private const REFUSAL = [
        'not composable' => 'This repository has no artisan of its own and no %s, so Laravel Boost'
            . ' was not run. This package does not install orchestra/testbench: a'
            . " package repository requires it itself. Run \"composer require --dev"
            . " orchestra/testbench\", or \"composer install\" if composer.json already"
            . ' requires it. A custom Composer bin-dir is not supported.',
        'unreadable' => '%s is not valid JSON, so Laravel Boost was not run. Its guidelines'
            . ' and skills will not compose until you fix or delete the file.',
        'refused' => 'Laravel Boost was not run: composing would overwrite hand-written content.',
        'no repair' => 'Edit the file yourself, then run the Composer command again. This package'
            . ' will not repair it: where your own text ends cannot be read from the file.',
    ];

    private const TEXT = [
        'no agents' => '%s names no agents, so Laravel Boost composes for the agents it detects on'
            . " this machine. Run \"%s\" once to choose them.",
        'exited' => 'Laravel Boost exited with an error. %s Boost registers its commands only when'
            . ' APP_ENV is local or APP_DEBUG is true.',
        'attached next step' => 'Its output is above.',
        'captured next step' => "Run \"%s\" to see why.",
        'attached nothing composed' => 'Laravel Boost ran but composed no agent file. Its output is'
            . " above. Run \"%s\" and choose at least one agent and the AI Guidelines feature.",
        'captured nothing composed' => 'Laravel Boost ran but composed no agent file: it found no'
            . " agent to compose for. Run \"%s\" and choose your agents.",
        'clean-code not direct' => 'This project does not require %s in its own composer.json,'
            . ' so Laravel Boost composes none of its guidelines.'
            . " Run \"composer require --dev %s\".",
    ];

    /**
     * Its guidelines compose only when the project requires it directly: Boost
     * skips the guidelines of a transitive dependency.
     */
    private const CLEAN_CODE = 'mike-bronner/clean-code';

    /**
     * What `register()` did to `boost.json`, or null when it did not run.
     */
    private ?string $registration = null;

    /**
     * @param  list<string>  $directRequirements  the packages the project's own
     *                                            composer.json requires, dev included
     */
    public function __construct(
        private IOInterface $inputOutput,
        private Terminal $terminal,
        private BoostHooks $hooks,
        private string $projectDir,
        private array $directRequirements = [],
        private ConsoleStyle $style = new ConsoleStyle(),
    ) {
    }

    /**
     * Name this package in `boost.json`, only where Boost can actually
     * compose: an app, or a package whose artisan shim has a Testbench to
     * boot. Anywhere else, registering would write a config file nothing ever
     * reads. Clean-code is named beside it only when the project requires it
     * directly: Boost never composes a transitive dependency, listed or not.
     */
    public function register(): void
    {
        $registrar = new BoostRegistrar();

        $this->registration = match ((new ProjectKind())->composesBoost($this->projectDir)) {
            true => $registrar->register(
                projectDir: $this->projectDir,
                package: InstalledPackage::NAME,
                replaces: [InstalledPackage::LEGACY_NAME],
                alongside: array_values(
                    array_intersect([self::CLEAN_CODE], $this->directRequirements),
                ),
            ),
            false => null,
        };
    }

    /**
     * The summary line for the registration, when it wrote `boost.json`.
     *
     * @return list<array{string, string}> type => path
     */
    public function summaryLines(): array
    {
        return match ($this->registration) {
            BoostRegistrar::REGISTERED => [['registered', BoostRegistrar::FILE]],
            default => [],
        };
    }

    /**
     * Count the registration: a new entry, an unchanged one, or a `boost.json`
     * skipped because it could not be read.
     */
    public function count(Tally $tally): void
    {
        match ($this->registration) {
            BoostRegistrar::REGISTERED => $tally->add(Tally::NEW),
            BoostRegistrar::UNCHANGED => $tally->add(Tally::UNCHANGED),
            BoostRegistrar::UNREADABLE => $tally->add(Tally::SKIPPED),
            default => null,
        };
    }

    /**
     * Run Boost, unless it cannot run here, `boost.json` could not be read, or
     * composing would damage an agent file. Boost is not run over a
     * `boost.json` this package cannot read: `boost:install` treats it as
     * empty and writes a fresh config over it, destroying the developer's
     * agent, guideline and MCP settings. A shim that could not be written was
     * reported with its cause, so a missing artisan is passed over in silence.
     *
     * A project that does not require clean-code directly is told to, first,
     * whatever happens next: the plugin never edits composer.json itself.
     */
    public function run(): void
    {
        $requiresCleanCode = in_array(self::CLEAN_CODE, $this->directRequirements, strict: true);
        $cleanCodeNotice = sprintf(
            self::TEXT['clean-code not direct'],
            self::CLEAN_CODE,
            self::CLEAN_CODE,
        );

        match ($requiresCleanCode) {
            true => null,
            false => $this->writeError('comment', $cleanCodeNotice),
        };

        $composes = (new ProjectKind())->composesBoost($this->projectDir);
        $notComposable = sprintf(self::REFUSAL['not composable'], ProjectKind::TESTBENCH);
        $unreadable = sprintf(self::REFUSAL['unreadable'], BoostRegistrar::FILE);
        $isUnreadable = $this->registration === BoostRegistrar::UNREADABLE;

        match (true) {
            $isUnreadable => $this->writeError('error', $unreadable),
            ! $composes => $this->writeError('comment', $notComposable),
            ! file_exists("{$this->projectDir}/" . ProjectKind::ARTISAN) => null,
            default => $this->guardAndCompose(),
        };
    }

    private function guardAndCompose(): void
    {
        $hazards = (new GuidelineGuard())->hazards($this->projectDir);
        $isAttached = $this->inputOutput
            ->isInteractive() && $this->terminal
            ->isAttached();

        match (true) {
            $hazards !== [] => $this->refuse($hazards),
            $isAttached => $this->composeAttached(),
            default => $this->composeCaptured(),
        };
    }

    /**
     * Report the agent files composing would damage, and say what to do.
     *
     * The files are left exactly as they are. Repairing one means guessing
     * where its hand-written section ends, and a wrong guess destroys the
     * content this check exists to save.
     *
     * @param  array<string, string>  $hazards  relativePath => reason
     */
    private function refuse(array $hazards): void
    {
        $this->writeError('error', self::REFUSAL['refused']);

        foreach ($hazards as $path => $reason) {
            $this->writeError('comment', "{$path} {$reason}");
        }

        $this->writeError('comment', self::REFUSAL['no repair']);
    }

    /**
     * Boost on the terminal asks for the agents and saves them, so it needs
     * no warning about them. The user watched the run, so its output is
     * already on screen.
     */
    private function composeAttached(): void
    {
        $inputOutput = $this->inputOutput;
        $startedAt = time();

        $inputOutput->write($this->description());
        $exitCode = (new SystemProcess())->passthru($this->hooks->interactiveCommand());
        $inputOutput->write('  Laravel Boost ', newline: false);

        $this->verify(
            new ProcessResult(exitCode: $exitCode, output: ''),
            $startedAt,
            self::TEXT['attached next step'],
            self::TEXT['attached nothing composed'],
        );
    }

    /**
     * A fresh clone has no `boost.json` agents: the file is gitignored, and a
     * captured install never records the agents it picks. Boost then composes
     * for whatever it detects on this machine, which may be nothing at all.
     */
    private function composeCaptured(): void
    {
        $inputOutput = $this->inputOutput;
        $hasAgents = (new BoostRegistrar())->hasAgents($this->projectDir);
        $noAgents = sprintf(
            self::TEXT['no agents'],
            BoostRegistrar::FILE,
            BoostHooks::INSTALL_COMMAND,
        );

        match ($hasAgents) {
            true => null,
            false => $this->writeError('comment', $noAgents),
        };

        $startedAt = time();
        $description = $this->description();
        $inputOutput->write("{$description} ", newline: false);

        $this->verify(
            (new SystemProcess())->capture(command: $this->hooks->command() . self::FEATURES),
            $startedAt,
            sprintf(self::TEXT['captured next step'], BoostHooks::INSTALL_COMMAND),
            self::TEXT['captured nothing composed'],
        );
    }

    /**
     * Report the run as done only when it both exited cleanly and composed.
     * Boost exits successfully when it found no agent to compose for, so a
     * zero exit alone would report "done" for a run that wrote nothing.
     */
    private function verify(
        ProcessResult $result,
        int $startedAt,
        string $nextStep,
        string $nothingComposed,
    ): void {
        $guard = new GuidelineGuard();

        match (true) {
            $result->failed() => $this->fail(sprintf(self::TEXT['exited'], $nextStep), $result),
            ! $guard->composedSince($this->projectDir, $startedAt) => $this->fail(
                sprintf($nothingComposed, BoostHooks::INSTALL_COMMAND),
                $result,
            ),
            default => $this->inputOutput
                ->write($this->style->wrap('info', 'done')),
        };
    }

    /**
     * Only a failure shows the command's output: a successful run stays one
     * summary line. The output is escaped, so a tag it prints is not styled.
     */
    private function fail(string $message, ProcessResult $result): void
    {
        $this->inputOutput
            ->write($this->style->wrap('error', 'failed'));
        $this->writeError('error', $message);

        foreach ($result->tail() as $line) {
            $this->inputOutput
                ->writeError($this->style->wrap('comment', "    │ {$this->style->escape($line)}"));
        }
    }

    /**
     * The description of the run, as it opens the run's line.
     */
    private function description(): string
    {
        $description = $this->hooks
            ->description();

        return <<<TEXT
  {$this->style
            ->wrap('info', $description)}
TEXT;
    }

    private function writeError(string $style, string $message): void
    {
        $this->inputOutput
            ->writeError($this->style->wrap($style, "  {$message}"));
    }
}
