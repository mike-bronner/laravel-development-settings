<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Plugin;

use Closure;
use Composer\IO\IOInterface;
use MikeBronner\DevelopmentSettings\Support\CheckedFile;
use MikeBronner\DevelopmentSettings\Support\PullRequestTemplates;
use RuntimeException;

final class PullRequestTemplateChoice
{
    private const ERROR = <<<TEXT
        <error>  %s</error>
        TEXT;

    private const NOT_DELETED = '%s was not deleted. %s';

    private const WITHDRAWN = '%s was taken back out,'
        . ' so the project keeps its own pull request template.'
        . ' The next interactive run asks again.';

    private const NOT_WITHDRAWN = '%s could not be taken back out. %s'
        . ' The shipped template and the project\'s own template now both exist.'
        . ' Delete %s by hand.';

    private array $held = [];
    private array $templates = [];
    private array $unreadable = [];

    public function __construct(
        private string $projectDir,
        private IOInterface $inputOutput,
        private PullRequestTemplates $finder = new PullRequestTemplates,
        private CheckedFile $file = new CheckedFile,
    ) {
    }

    public function hold(array $created): array
    {
        $shipped = PullRequestTemplates::SHIPPED;
        $found = match (array_key_exists($shipped, $created)) {
            true => $this->finder
                ->inspect($this->projectDir),
            false => ['templates' => [], 'unreadable' => []],
        };
        ['templates' => $this->templates, 'unreadable' => $this->unreadable] = $found;
        $this->held = match ($this->templates === [] && $this->unreadable === []) {
            true => [],
            false => array_intersect_key($created, [$shipped => true]),
        };

        return array_diff_key($created, $this->held);
    }

    public function summaryLines(): array
    {
        return collect(array_keys($this->held))
            ->map(fn (string $path): array => [$this->heldType(), $path])
            ->all();
    }

    public function settle(Consent $consent, Tally $tally, Closure $write): void
    {
        foreach ($this->held as $path => $source) {
            $isReplaced = $consent->replaceTemplate($path, $this->templates, $this->unreadable)
                && $write($path, $source);

            match ($isReplaced) {
                true => $this->replaced($tally),
                false => $tally->add(Tally::SKIPPED),
            };
        }
    }

    private function heldType(): string
    {
        return match ($this->templates) {
            [] => 'unsearched',
            default => 'held',
        };
    }

    private function replaced(Tally $tally): void
    {
        $remaining = collect($this->templates)
            ->reject(fn (string $template): bool => $this->deleted($template));
        $isWithdrawn = $remaining->isNotEmpty() && $this->withdrawn();

        $tally->add(Tally::REMOVED, count($this->templates) - $remaining->count());
        $tally->add(match ($isWithdrawn) {
            true => Tally::SKIPPED,
            false => Tally::NEW,
        });
    }

    private function deleted(string $template): bool
    {
        try {
            $this->file
                ->unlink("{$this->projectDir}/{$template}");
        } catch (RuntimeException $exception) {
            $this->error(sprintf(self::NOT_DELETED, $template, $exception->getMessage()));

            return false;
        }

        return true;
    }

    private function withdrawn(): bool
    {
        $shipped = PullRequestTemplates::SHIPPED;

        try {
            $this->file
                ->unlink("{$this->projectDir}/{$shipped}");
        } catch (RuntimeException $exception) {
            $cause = $exception->getMessage();
            $this->error(sprintf(self::NOT_WITHDRAWN, $shipped, $cause, $shipped));

            return false;
        }

        $this->error(sprintf(self::WITHDRAWN, $shipped));

        return true;
    }

    private function error(string $message): void
    {
        $this->inputOutput
            ->writeError(sprintf(self::ERROR, $message));
    }
}
