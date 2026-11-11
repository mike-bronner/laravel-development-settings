<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Plugin;

use Composer\IO\IOInterface;
use Laravel\Prompts\Prompt;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

final class Consent
{
    private const COMMENT = <<<TEXT
        <comment>  %s</comment>
        TEXT;

    private const ERROR = <<<TEXT
        <error>  %s</error>
        TEXT;

    private const TEXT = [
        'overwrite' => 'Overwrite locally modified files?',
        'convert' => 'Add the sync marker to these locally modified files?',
        'delete' => 'Delete files removed upstream that you have modified locally?',
        'overwrite hint' => 'Space to toggle, Enter to confirm.',
        'convert hint' => 'Your whole file moves below the marker.'
            . ' Unselected files are kept as they are.',
        'delete hint' => 'Unselected files are kept. Space to toggle, Enter to confirm.',
        'convert kept' => '%d locally-modified file(s) have no sync marker and were not updated.'
            . ' Run composer interactively to add it; your entries are kept below it.',
        'delete kept' => '%d locally-modified file(s) removed upstream were kept.'
            . ' Delete manually if no longer needed.',
        'unconvertible' => '%s is locally modified and has no sync marker, so it was not updated.'
            . ' To get the shipped file, delete it and run composer again,'
            . ' then put your own lines above its marker line.',
        'refused' => '%s holds the sync marker more than once, so it was not touched.'
            . ' Keep one marker line and run composer again.',
        'template' => 'Keep the project\'s own pull request template, or replace it?',
        'template hint' => 'Found: %s. Replace deletes these files.',
        'template keep' => 'Keep the project\'s template',
        'template replace' => 'Replace it with %s',
        'template kept' => '%s was not written, because the project has its own pull request'
            . ' template: %s. Run composer interactively to replace it with the shipped one.',
        'template unreadable' => '%s was not written, because a folder could not be searched'
            . ' for the project\'s own pull request template. %s',
    ];

    private const REPLACE = 'replace';

    public function __construct(private IOInterface $inputOutput)
    {
    }

    public function overwrite(array $modified): array
    {
        return match ($modified) {
            [] => [],
            default => $this->select($modified, 'overwrite'),
        };
    }

    public function convert(array $unmarked): array
    {
        return $this->askOrKeep($unmarked, 'convert');
    }

    public function orphansToDelete(array $protectedOrphans): array
    {
        return $this->askOrKeep($protectedOrphans, 'delete');
    }

    public function explainUnconvertible(array $unconvertible): void
    {
        foreach ($unconvertible as $path) {
            $this->kept(sprintf(self::TEXT['unconvertible'], $path));
        }
    }

    public function explainRefused(array $refused): void
    {
        $inputOutput = $this->inputOutput;

        foreach ($refused as $path) {
            $inputOutput->writeError(sprintf(self::ERROR, sprintf(self::TEXT['refused'], $path)));
        }
    }

    public function replaceTemplate(string $shipped, array $templates, array $unreadable): bool
    {
        $found = implode(', ', $templates);
        $inputOutput = $this->inputOutput;

        return match (true) {
            $unreadable !== [] => $this->keptTemplate(sprintf(
                    self::TEXT['template unreadable'],
                    $shipped,
                    implode(' ', $unreadable),
                )),
            $inputOutput->isInteractive() => $this->chooseTemplate($shipped, $found),
            default => $this->keptTemplate(sprintf(self::TEXT['template kept'], $shipped, $found)),
        };
    }

    private function chooseTemplate(string $shipped, string $found): bool
    {
        Prompt::interactive(true);

        return select(
                label: self::TEXT['template'],
                options: [
                    'keep' => self::TEXT['template keep'],
                    self::REPLACE => sprintf(self::TEXT['template replace'], $shipped),
                ],
                default: 'keep',
                hint: sprintf(self::TEXT['template hint'], $found),
            ) === self::REPLACE;
    }

    private function keptTemplate(string $message): bool
    {
        $this->kept($message);

        return false;
    }

    private function askOrKeep(array $paths, string $question): array
    {
        $inputOutput = $this->inputOutput;

        return match (true) {
            $paths === [] => [],
            $inputOutput->isInteractive() => $this->select($paths, $question),
            default => $this->kept(sprintf(self::TEXT["{$question} kept"], count($paths))),
        };
    }

    private function select(array $paths, string $question): array
    {
        $inputOutput = $this->inputOutput;
        Prompt::interactive($inputOutput->isInteractive());

        return multiselect(
                label: self::TEXT[$question],
                options: array_combine($paths, $paths),
                default: [],
                required: false,
                hint: self::TEXT["{$question} hint"],
            );
    }

    private function kept(string $message): array
    {
        $inputOutput = $this->inputOutput;
        $inputOutput->writeError(sprintf(self::COMMENT, $message));

        return [];
    }
}
