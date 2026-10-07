<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Plugin;

use Composer\IO\IOInterface;
use MikeBronner\DevelopmentSettings\Support\IgnoreOverrides;

/**
 * Lists, on every run, the lines below the `.gitignore` marker that override
 * the shipped rules above it. The lines are the project's, so they are named
 * and never changed.
 */
final class IgnoreOverrideNotice
{
    public const FILE = '.gitignore';

    private const TEXT = [
        'heading' => '%s: these lines below the sync marker override the shipped rules above it,'
            . ' because the last matching rule wins:',
        'advice' => 'They are yours, so they are left as they are. Delete each one you did not'
            . ' mean to keep.',
    ];

    public function __construct(
        private IOInterface $inputOutput,
        private string $projectDir,
        private ConsoleStyle $style = new ConsoleStyle,
    ) {
    }

    /**
     * @param  array<string, string>  $files  targetPath => absoluteSourcePath, as synced
     */
    public function warn(array $files): void
    {
        $target = "{$this->projectDir}/" . self::FILE;
        $source = data_get($files, [self::FILE]) ?? null;

        $overrides = match (true) {
            $source === null,
            is_link($target),
            ! is_file($target),
            ! is_readable($target) => [],
            default => (new IgnoreOverrides)->find(
                (string) file_get_contents($target),
                (string) file_get_contents($source),
            ),
        };

        match ($overrides) {
            [] => null,
            default => $this->list($overrides),
        };
    }

    /**
     * @param  array<int, array{string, list<string>}>  $overrides
     */
    private function list(array $overrides): void
    {
        $this->write(sprintf(self::TEXT['heading'], self::FILE));

        foreach ($overrides as $lineNumber => [$line, $reasons]) {
            $reason = implode('; ', $reasons);
            $this->write("  line {$lineNumber}: {$this->style->escape($line)} ({$reason})");
        }

        $this->write(self::TEXT['advice']);
    }

    private function write(string $message): void
    {
        $this->inputOutput
            ->writeError($this->style->wrap('comment', "  {$message}"));
    }
}
