<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Plugin;

use Composer\IO\IOInterface;
use Laravel\Prompts\Prompt;

use function Laravel\Prompts\multiselect;

/**
 * Asks the developer which kept files a sync may change after all.
 *
 * Nothing is ever selected by default. A run that cannot ask, because
 * Composer is not interactive, changes nothing it would have asked about, and
 * says what it kept and why. Each question has its label, its hint, and what
 * a run that cannot ask says instead.
 */
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
        'refused' => '%s holds the sync marker more than once, so it was not touched.'
            . ' Keep one marker line and run composer again.',
    ];

    public function __construct(private IOInterface $inputOutput)
    {
    }

    /**
     * The locally modified files to overwrite with the shipped version. A run
     * that cannot ask selects none, silently: the summary already names them.
     *
     * @param  list<string>  $modified
     * @return list<string>
     */
    public function overwrite(array $modified): array
    {
        return match ($modified) {
            [] => [],
            default => $this->select($modified, 'overwrite'),
        };
    }

    /**
     * The edited files with no sync marker to convert. An edited file with no
     * marker cannot be split into the package's part and the project's, so it
     * is only converted on consent. The conversion loses nothing: the whole
     * file moves below the marker.
     *
     * @param  list<string>  $unmarked
     * @return list<string>
     */
    public function convert(array $unmarked): array
    {
        return $this->askOrKeep($unmarked, 'convert');
    }

    /**
     * The files removed upstream, but edited here, to delete.
     *
     * @param  list<string>  $protectedOrphans
     * @return list<string>
     */
    public function orphansToDelete(array $protectedOrphans): array
    {
        return $this->askOrKeep($protectedOrphans, 'delete');
    }

    /**
     * Say why each file holding the marker more than once was not touched.
     *
     * @param  list<string>  $refused
     */
    public function explainRefused(array $refused): void
    {
        $inputOutput = $this->inputOutput;

        foreach ($refused as $path) {
            $inputOutput->writeError(sprintf(self::ERROR, sprintf(self::TEXT['refused'], $path)));
        }
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function askOrKeep(array $paths, string $question): array
    {
        $inputOutput = $this->inputOutput;

        return match (true) {
            $paths === [] => [],
            $inputOutput->isInteractive() => $this->select($paths, $question),
            default => $this->kept(sprintf(self::TEXT["{$question} kept"], count($paths))),
        };
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
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

    /**
     * Say what was kept without asking, and select nothing.
     *
     * @return list<string>
     */
    private function kept(string $message): array
    {
        $inputOutput = $this->inputOutput;
        $inputOutput->writeError(sprintf(self::COMMENT, $message));

        return [];
    }
}
