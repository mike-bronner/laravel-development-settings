<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Plugin;

use Composer\IO\IOInterface;
use MikeBronner\DevelopmentSettings\Support\BoostHooks;
use MikeBronner\DevelopmentSettings\Support\FlareMcpServer;
use MikeBronner\DevelopmentSettings\Support\ProcessResult;
use MikeBronner\DevelopmentSettings\Support\ProjectKind;
use MikeBronner\DevelopmentSettings\Support\SystemProcess;

final class FlareMcp
{
    private const TEXT = [
        'adding' => 'Adding the Flare MCP server...',
        'failed' => 'The Flare MCP server was not added to every agent Laravel Boost configured'
            . " MCP for. Run \"%s\" to see why.",
    ];

    public function __construct(
        private IOInterface $inputOutput,
        private string $projectDir,
        private BoostHooks $hooks,
        private ConsoleStyle $style = new ConsoleStyle,
    ) {
    }

    public function settle(?int $startedAt): void
    {
        match ($startedAt !== null && (new FlareMcpServer)->isWantedIn($this->projectDir)) {
            true => $this->add(),
            false => null,
        };
    }

    private function add(): void
    {
        [$hooks, $shownCommand] = match ((new ProjectKind)->isTestbenchPackage($this->projectDir)) {
            true => [$this->hooks->throughTestbench(), BoostHooks::TESTBENCH_FLARE_MCP_COMMAND],
            false => [$this->hooks, BoostHooks::FLARE_MCP_COMMAND],
        };

        $this->inputOutput
            ->write("  {$this->style->wrap('info', self::TEXT['adding'])} ", newline: false);
        $result = (new SystemProcess)->capture(
                command: $hooks->flareMcpCommand(),
                workingDirectory: $this->projectDir,
            );

        match ($result->failed()) {
            true => $this->fail($result, $shownCommand),
            false => $this->inputOutput
                ->write($this->style->wrap('info', 'done')),
        };
    }

    private function fail(ProcessResult $result, string $command): void
    {
        $this->inputOutput
            ->write($this->style->wrap('error', 'failed'));
        $this->inputOutput
            ->writeError($this->style->wrap(
                    'error',
                    "  {$this->style->escape(sprintf(self::TEXT['failed'], $command))}",
                ));

        foreach ($result->tail() as $line) {
            $this->inputOutput
                ->writeError($this->style->wrap('comment', "    │ {$this->style->escape($line)}"));
        }
    }
}
