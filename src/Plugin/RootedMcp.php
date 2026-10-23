<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Plugin;

use Composer\IO\IOInterface;
use MikeBronner\DevelopmentSettings\Support\BoostHooks;
use MikeBronner\DevelopmentSettings\Support\McpConfigRewriter;
use MikeBronner\DevelopmentSettings\Support\ProcessResult;
use MikeBronner\DevelopmentSettings\Support\ProjectKind;
use MikeBronner\DevelopmentSettings\Support\RootedTestbench;
use MikeBronner\DevelopmentSettings\Support\SystemProcess;

final class RootedMcp
{
    private const TEXT = [
        'rewritten' => 'Pointed the %s MCP server in %s at %s: this repository has no artisan.',
        'not rewritten' => '%s: %s, so the %s MCP server it configures does not start. Point it at'
            . " \"%s boost:mcp\" yourself, or run the Composer command again.",
        'unread' => '%s: %s, so it was not checked for a %s MCP server.',
        'checking' => 'Checking Boost MCP tool calls...',
        'check failed' => 'Boost MCP tool calls in this repository would run through artisan,'
            . " which does not exist. This package's service provider replaces Boost's tool"
            . ' executor with one that runs %s instead, and either the provider did not load or'
            . " Boost no longer matches what it replaces. Run \"%s\" to see why.",
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
        match ($startedAt !== null && (new ProjectKind)->isTestbenchPackage($this->projectDir)) {
            true => $this->rewriteAndCheck($startedAt),
            false => null,
        };
    }

    private function rewriteAndCheck(int $startedAt): void
    {
        $result = (new McpConfigRewriter)->rewrite($this->projectDir, $startedAt);
        $outcomes = collect(data_get($result, 'configs'));
        $rewritten = $outcomes->filter(fn (?string $failure): bool => $failure === null)
            ->keys();

        match ($rewritten->isEmpty()) {
            true => null,
            false => $this->writeError('info', sprintf(
                self::TEXT['rewritten'],
                RootedTestbench::MCP_SERVER,
                $rewritten->implode(', '),
                RootedTestbench::SCRIPT,
            )),
        };

        $outcomes->filter()
            ->each(fn (string $failure, string $path): mixed => $this->writeError('error', sprintf(
                    self::TEXT['not rewritten'],
                    $path,
                    rtrim($failure, '.'),
                    RootedTestbench::MCP_SERVER,
                    BoostHooks::TESTBENCH,
                )));

        collect(data_get($result, 'unread'))
            ->each(fn (string $cause, string $path): mixed => $this->writeError('comment', sprintf(
                    self::TEXT['unread'],
                    $path,
                    rtrim($cause, '.'),
                    RootedTestbench::MCP_SERVER,
                )));

        $this->check();
    }

    private function check(): void
    {
        $this->inputOutput
            ->write("  {$this->style->wrap('info', self::TEXT['checking'])} ", newline: false);
        $result = (new SystemProcess)->capture(
                command: $this->hooks
                    ->mcpCheckCommand(),
                workingDirectory: $this->projectDir,
            );

        match ($result->failed()) {
            true => $this->fail($result),
            false => $this->inputOutput
                ->write($this->style->wrap('info', 'done')),
        };
    }

    private function fail(ProcessResult $result): void
    {
        $this->inputOutput
            ->write($this->style->wrap('error', 'failed'));
        $this->writeError('error', sprintf(
                self::TEXT['check failed'],
                RootedTestbench::SCRIPT,
                BoostHooks::MCP_CHECK_COMMAND,
            ));

        foreach ($result->tail() as $line) {
            $this->inputOutput
                ->writeError($this->style->wrap('comment', "    │ {$this->style->escape($line)}"));
        }
    }

    private function writeError(string $style, string $message): void
    {
        $this->inputOutput
            ->writeError($this->style->wrap($style, "  {$this->style->escape($message)}"));
    }
}
