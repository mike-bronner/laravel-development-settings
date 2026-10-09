<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Plugin;

use Composer\IO\IOInterface;
use MikeBronner\DevelopmentSettings\Support\BoostHooks;
use MikeBronner\DevelopmentSettings\Support\BoostRegistrar;
use MikeBronner\DevelopmentSettings\Support\GuidelineGuard;
use MikeBronner\DevelopmentSettings\Support\InstalledPackage;
use MikeBronner\DevelopmentSettings\Support\PhpGuideline;
use MikeBronner\DevelopmentSettings\Support\ProcessResult;
use MikeBronner\DevelopmentSettings\Support\ProjectKind;
use MikeBronner\DevelopmentSettings\Support\SystemProcess;
use MikeBronner\DevelopmentSettings\Support\Terminal;

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

    private const DISCOVERY = [
        'running' => 'Rebuilding the package discovery cache...',
        'failed' => "Package discovery exited with an error. Run \"%s\" to see why: a service"
            . ' provider installed since the cache was written may not load in Boost.',
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
        'php core composed' => 'Laravel Boost composed its own PHP guideline, which asks for'
            . " PHPDoc blocks, into %s. This package's service provider leaves that guideline"
            . " out by adding \"%s\" to boost.guidelines.exclude, and it did not run. In a package"
            . ' repository, bootstrap/cache/packages.php is most likely older than the provider:'
            . ' this package rebuilds it before Boost runs only when the repository has no'
            . ' artisan of its own. If artisan is an edited copy of the shim earlier releases'
            . ' wrote, delete it and run the Composer command again. Otherwise, check that'
            . ' package discovery is not turned off for %s.',
        'clean-code not direct' => 'This project does not require %s in its own composer.json,'
            . ' so Laravel Boost composes none of its guidelines.'
            . " Run \"composer require --dev %s\".",
    ];

    private const CLEAN_CODE = 'mike-bronner/clean-code';

    private ?string $registration = null;
    private ?array $composedFiles = null;

    public function __construct(
        private IOInterface $inputOutput,
        private Terminal $terminal,
        private BoostHooks $hooks,
        private string $projectDir,
        private array $directRequirements = [],
        private ConsoleStyle $style = new ConsoleStyle,
    ) {
    }

    public function register(): void
    {
        $registrar = new BoostRegistrar;

        $this->registration = match ((new ProjectKind)->composesBoost($this->projectDir)) {
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

    public function summaryLines(): array
    {
        return match ($this->registration) {
            BoostRegistrar::REGISTERED => [['registered', BoostRegistrar::FILE]],
            default => [],
        };
    }

    public function count(Tally $tally): void
    {
        match ($this->registration) {
            BoostRegistrar::REGISTERED => $tally->add(Tally::NEW),
            BoostRegistrar::UNCHANGED => $tally->add(Tally::UNCHANGED),
            BoostRegistrar::UNREADABLE => $tally->add(Tally::SKIPPED),
            default => null,
        };
    }

    public function composedFiles(): ?array
    {
        return $this->composedFiles;
    }

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

        $composes = (new ProjectKind)->composesBoost($this->projectDir);
        $notComposable = sprintf(self::REFUSAL['not composable'], ProjectKind::TESTBENCH);
        $unreadable = sprintf(self::REFUSAL['unreadable'], BoostRegistrar::FILE);
        $isUnreadable = $this->registration === BoostRegistrar::UNREADABLE;

        match (true) {
            $isUnreadable => $this->writeError('error', $unreadable),
            ! $composes => $this->writeError('comment', $notComposable),
            default => $this->guardAndCompose(),
        };
    }

    private function guardAndCompose(): void
    {
        $hazards = (new GuidelineGuard)->hazards($this->projectDir);

        match ($hazards) {
            [] => $this->discoverAndCompose(),
            default => $this->refuse($hazards),
        };
    }

    private function discoverAndCompose(): void
    {
        $isAttached = $this->inputOutput
            ->isInteractive() && $this->terminal
            ->isAttached();
        $isTestbenchPackage = (new ProjectKind)->isTestbenchPackage($this->projectDir);
        $hooks = match ($isTestbenchPackage) {
            true => $this->hooks
                ->throughTestbench(),
            false => $this->hooks,
        };

        match ($isTestbenchPackage) {
            true => $this->discoverPackages($hooks),
            false => null,
        };

        match ($isAttached) {
            true => $this->composeAttached($hooks),
            false => $this->composeCaptured($hooks),
        };
    }

    private function discoverPackages(BoostHooks $hooks): void
    {
        $this->inputOutput
            ->write("{$this->line(self::DISCOVERY['running'])} ", newline: false);
        $result = (new SystemProcess)->capture(
                command: $hooks->discoverCommand(),
                workingDirectory: $this->projectDir,
            );
        $failure = sprintf(self::DISCOVERY['failed'], BoostHooks::DISCOVER_COMMAND);

        match ($result->failed()) {
            true => $this->fail($failure, $result),
            false => $this->inputOutput
                ->write($this->style->wrap('info', 'done')),
        };
    }

    private function refuse(array $hazards): void
    {
        $this->writeError('error', self::REFUSAL['refused']);

        foreach ($hazards as $path => $reason) {
            $this->writeError('comment', "{$path} {$reason}");
        }

        $this->writeError('comment', self::REFUSAL['no repair']);
    }

    private function composeAttached(BoostHooks $hooks): void
    {
        $inputOutput = $this->inputOutput;
        $startedAt = time();

        $inputOutput->write($this->line($hooks->description()));
        $exitCode = (new SystemProcess)->passthru($hooks->interactiveCommand(), $this->projectDir);
        $inputOutput->write('  Laravel Boost ', newline: false);

        $this->verify(
                new ProcessResult(exitCode: $exitCode, output: ''),
                $startedAt,
                self::TEXT['attached next step'],
                sprintf(self::TEXT['attached nothing composed'], $hooks->installCommand()),
            );
    }

    // phpcs:disable CleanCode.Pattern.AvoidDuplicateCodeBlocks.Found
    private function composeCaptured(BoostHooks $hooks): void
    {
        $inputOutput = $this->inputOutput;
        $hasAgents = (new BoostRegistrar)->hasAgents($this->projectDir);
        $installCommand = $hooks->installCommand();
        $noAgents = sprintf(self::TEXT['no agents'], BoostRegistrar::FILE, $installCommand);

        match ($hasAgents) {
            true => null,
            false => $this->writeError('comment', $noAgents),
        };

        $startedAt = time();
        $description = $this->line($hooks->description());
        $inputOutput->write("{$description} ", newline: false);

        $this->verify(
                (new SystemProcess)->capture(
                        command: $hooks->command() . self::FEATURES,
                        workingDirectory: $this->projectDir,
                    ),
                $startedAt,
                sprintf(self::TEXT['captured next step'], $installCommand),
                sprintf(self::TEXT['captured nothing composed'], $installCommand),
            );
    }

    private function verify(
        ProcessResult $result,
        int $startedAt,
        string $nextStep,
        string $nothingComposed,
    ): void {
        match ($result->failed()) {
            true => $this->fail(sprintf(self::TEXT['exited'], $nextStep), $result),
            false => $this->verifyComposed(
                (new GuidelineGuard)->composedBlocks($this->projectDir, $startedAt),
                $result,
                $nothingComposed,
            ),
        };
    }
    // phpcs:enable CleanCode.Pattern.AvoidDuplicateCodeBlocks.Found

    private function verifyComposed(
        array $blocks,
        ProcessResult $result,
        string $nothingComposed,
    ): void {
        $phpCoreFiles = collect($blocks)
            ->filter(fn (string $block): bool => str_contains($block, PhpGuideline::DOCBLOCK_RULE))
            ->keys()
            ->all();

        match (true) {
            $blocks === [] => $this->fail($nothingComposed, $result),
            $phpCoreFiles !== [] => $this->fail(
                sprintf(
                    self::TEXT['php core composed'],
                    implode(', ', $phpCoreFiles),
                    PhpGuideline::BOOST_KEY,
                    InstalledPackage::NAME,
                ),
                $result,
            ),
            default => $this->done(array_keys($blocks)),
        };
    }

    private function done(array $composedFiles): void
    {
        $this->composedFiles = $composedFiles;
        $this->inputOutput
            ->write($this->style->wrap('info', 'done'));
    }

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

    private function line(string $description): string
    {
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
