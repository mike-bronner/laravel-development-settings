<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Plugin;

use Composer\IO\IOInterface;
use Laravel\Prompts\Prompt;
use MikeBronner\DevelopmentSettings\Support\ContributionDetector;
use MikeBronner\DevelopmentSettings\Support\Contributor;
use MikeBronner\DevelopmentSettings\Support\InstalledPackage;
use MikeBronner\DevelopmentSettings\Support\ManifestReader;
use MikeBronner\DevelopmentSettings\Support\PackageConfig;
use MikeBronner\DevelopmentSettings\Support\SystemProcess;

use function Laravel\Prompts\confirm;

/**
 * Before an update, names the installed guideline and skill sources the
 * developer edited in place, which the update would overwrite.
 *
 * An interactive run offers to contribute them upstream, and the default is
 * no. A run that cannot ask names the files and the command to run instead.
 * Nothing is opened without consent.
 */
final class ContributionPrompt
{
    private const COMMAND = 'vendor/bin/dev-settings-contribute.php';

    private const TEXT = [
        'found' => 'You have %d local edit(s) to shared development-settings files:',
        'question' => 'Contribute these to development-settings before updating?',
        'lost' => '  These live in vendor and will be lost on update.'
            . " Run \"%s\" to PR them upstream.",
        'skipped' => "  Skipped — run \"%s\" later to contribute.",
    ];

    private const SUCCEEDED = 0;

    public function __construct(
        private IOInterface $inputOutput,
        private string $projectDir,
        private string $packageDir,
        private ConsoleStyle $style = new ConsoleStyle(),
    ) {
    }

    public function offer(): void
    {
        $config = (new InstalledPackage($this->projectDir))->config($this->packageDir);
        $manifestPath = "{$this->packageDir}/" . ContributionDetector::MANIFEST_FILE;
        $sources = (new ManifestReader())->read($manifestPath);
        $modified = (new ContributionDetector())->modified(
                packageDir: $this->packageDir,
                directories: $config->entries(PackageConfig::CAPTURE),
                sources: $sources,
                ignore: $config->entries(PackageConfig::IGNORE),
            );

        match ($modified) {
            [] => null,
            default => $this->announce($modified),
        };
    }

    /**
     * @param  array<string, string>  $modified
     */
    private function announce(array $modified): void
    {
        $inputOutput = $this->inputOutput;
        $inputOutput->write('');
        $inputOutput->write($this->comment(sprintf(self::TEXT['found'], count($modified))));

        foreach (array_keys($modified) as $path) {
            $inputOutput->write("  {$this->comment("· {$path}")}");
        }

        match ($inputOutput->isInteractive()) {
            true => $this->ask($modified),
            false => $inputOutput->writeError($this->commented(self::TEXT['lost'])),
        };
    }

    /**
     * @param  array<string, string>  $modified
     */
    private function ask(array $modified): void
    {
        Prompt::interactive(true);

        match (confirm(label: self::TEXT['question'], default: false)) {
            true => $this->contribute($modified),
            false => $this->inputOutput
                ->write($this->commented(self::TEXT['skipped'])),
        };
    }

    /**
     * @param  array<string, string>  $modified
     */
    private function contribute(array $modified): void
    {
        $contributor = new Contributor(new SystemProcess());
        ['status' => $status, 'message' => $message] = $contributor->open(
                modified: $modified,
                branch: $contributor->branchFor($this->projectDir),
                cloneDir: $contributor->cloneDirectory(),
                token: (string) getenv('DEVELOPER_SETTINGS_TOKEN'),
            );

        $this->inputOutput
            ->write(match ($status) {
            self::SUCCEEDED => $this->style
                ->wrap('info', "  {$message}"),
            default => $this->style
                ->wrap('error', "  {$message}"),
            });
    }

    /**
     * One of the texts naming the contribute command, as a comment.
     */
    private function commented(string $text): string
    {
        return $this->comment(sprintf($text, self::COMMAND));
    }

    private function comment(string $text): string
    {
        return $this->style
            ->wrap('comment', $text);
    }
}
