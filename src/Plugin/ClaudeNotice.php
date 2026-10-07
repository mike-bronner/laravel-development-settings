<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Plugin;

use Composer\IO\IOInterface;
use MikeBronner\DevelopmentSettings\Support\ClaudeImport;

/**
 * After a clean Boost run, writes a missing `CLAUDE.md` as an import of
 * `AGENTS.md`, or says that an existing one does not import it. Claude Code
 * reads `CLAUDE.md`, and the provider points Boost's Claude Code guidelines at
 * `AGENTS.md`.
 */
final class ClaudeNotice
{
    private const TEXT = [
        ClaudeImport::CREATED => [
            'info',
            "Wrote CLAUDE.md, which imports AGENTS.md: Laravel Boost composes Claude Code's"
                . ' guidelines into AGENTS.md, and Claude Code reads CLAUDE.md. Commit it.',
        ],
        ClaudeImport::NOT_IMPORTED => [
            'comment',
            "CLAUDE.md does not import AGENTS.md, where this package points Laravel Boost's"
                . ' Claude Code guidelines, so Claude Code does not read them. Add the line'
                . " \"@AGENTS.md\" to CLAUDE.md, and remove any Laravel Boost block it still holds:"
                . ' Boost no longer updates it.',
        ],
        ClaudeImport::FAILED => [
            'error',
            'CLAUDE.md could not be written, so Claude Code does not read the guidelines Laravel'
                . " Boost composed into AGENTS.md. Create it with the line \"@AGENTS.md\".",
        ],
    ];

    public function __construct(
        private IOInterface $inputOutput,
        private string $projectDir,
        private ConsoleStyle $style = new ConsoleStyle,
    ) {
    }

    /**
     * @param  list<string>|null  $composedFiles  what a clean Boost run composed, or null
     */
    public function settle(?array $composedFiles): void
    {
        $outcome = match ($composedFiles) {
            null => null,
            default => (new ClaudeImport)->settle($this->projectDir, $composedFiles),
        };
        [$style, $message] = data_get(self::TEXT, $outcome ?? '', [null, null]);

        match ($message) {
            null => null,
            default => $this->inputOutput
                ->writeError($this->style->wrap($style, "  {$message}")),
        };
    }
}
