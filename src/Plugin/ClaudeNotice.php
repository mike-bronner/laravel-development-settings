<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Plugin;

use Composer\IO\IOInterface;
use MikeBronner\DevelopmentSettings\Support\ClaudeRemoval;
use RuntimeException;

final class ClaudeNotice
{
    private const REMOVED = 'Deleted CLAUDE.md: Laravel Boost composes the guidelines of'
        . ' Claude Code into AGENTS.md, and Claude Code reads AGENTS.md only when no CLAUDE.md'
        . ' exists. If git tracks CLAUDE.md, commit the deletion.';

    private const FAILED = 'CLAUDE.md could not be deleted, so Claude Code does not read AGENTS.md,'
        . ' where Laravel Boost composes its guidelines. Delete CLAUDE.md yourself. %s';

    public function __construct(
        private IOInterface $inputOutput,
        private string $projectDir,
        private ConsoleStyle $style = new ConsoleStyle,
    ) {
    }

    public function settle(array $composedFiles): void
    {
        $removal = new ClaudeRemoval;

        match ($removal->isRemovable($this->projectDir, $composedFiles)) {
            true => $this->remove($removal),
            false => null,
        };
    }

    private function remove(ClaudeRemoval $removal): void
    {
        try {
            $removal->remove($this->projectDir);
        } catch (RuntimeException $exception) {
            $this->write('error', sprintf(self::FAILED, $exception->getMessage()));

            return;
        }

        $this->write('info', self::REMOVED);
    }

    private function write(string $style, string $message): void
    {
        $this->inputOutput
            ->writeError($this->style->wrap($style, "  {$this->style->escape($message)}"));
    }
}
