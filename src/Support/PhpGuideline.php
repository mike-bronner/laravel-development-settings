<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use RuntimeException;

final class PhpGuideline
{
    public const BOOST_KEY = 'php';

    public const SOURCE = 'vendor/laravel/boost/.ai/php/core.blade.php';

    public const TARGET = 'resources/boost/guidelines/05-php.blade.php';

    public const DOCBLOCK_RULE = 'Prefer PHPDoc blocks';

    public const NO_DOCBLOCKS_RULE = 'No comments or docblocks unless explicitly requested.'
        . ' Exception: test section markers.';

    private const REPLACED_RULE = self::DOCBLOCK_RULE . ' over inline comments.'
        . ' Only add inline comments for exceptionally complex logic.';

    private const REMOVED_RULE = 'Use array shape type definitions in PHPDoc blocks.';

    private const BULLET = '- ';

    private const FORBIDDEN = 'PHPDoc';

    private const SUCCEEDED = 0;

    private const FAILED = 1;

    private const TEXT = [
        'missing' => "Laravel Boost's php/core no longer holds the line \"%s\".",
        'forbidden' => "Laravel Boost's php/core still mentions %s once its two known lines"
            . ' are removed.',
        'not generated' => '%s was not generated, and was left as it was: %s',
        'generated' => "%s generated from Laravel Boost's php/core.\n",
        'current' => "%s is up to date with Laravel Boost's php/core.\n",
        'stale' => "%s is out of date with Laravel Boost's php/core."
            . " Run: composer dev-settings:guideline, then composer dev-settings:manifest\n",
    ];

    public function __construct(
        private string $packageDir,
        private mixed $output,
        private mixed $errors,
        private CheckedFile $file = new CheckedFile,
    ) {
    }

    public function render(string $boostGuideline): string
    {
        $lines = explode("\n", $boostGuideline);
        $replaced = self::BULLET . self::REPLACED_RULE;
        $removed = self::BULLET . self::REMOVED_RULE;

        foreach ([$replaced, $removed] as $line) {
            match (in_array($line, $lines, strict: true)) {
                true => null,
                false => throw new RuntimeException(sprintf(self::TEXT['missing'], $line)),
            };
        }

        $rendered = collect($lines)
            ->reject(fn (string $line): bool => $line === $removed)
            ->map(fn (string $line): string => match ($line) {
                $replaced => self::BULLET . self::NO_DOCBLOCKS_RULE,
                default => $line,
            })
            ->implode("\n");

        return match (str_contains($rendered, self::FORBIDDEN)) {
            true => throw new RuntimeException(sprintf(self::TEXT['forbidden'], self::FORBIDDEN)),
            false => $rendered,
        };
    }

    public function generate(): int
    {
        try {
            $rendered = $this->rendered();
            $this->file
                ->write($this->path(self::TARGET), $rendered);
        } catch (RuntimeException $exception) {
            return $this->notGenerated($exception);
        }

        $generated = sprintf(self::TEXT['generated'], self::TARGET);

        return $this->report($this->output, $generated, self::SUCCEEDED);
    }

    public function check(): int
    {
        try {
            $rendered = $this->rendered();
        } catch (RuntimeException $exception) {
            return $this->notGenerated($exception);
        }

        $target = $this->path(self::TARGET);
        $isCurrent = file_exists($target) && file_get_contents($target) === $rendered;

        $current = sprintf(self::TEXT['current'], self::TARGET);
        $stale = sprintf(self::TEXT['stale'], self::TARGET);

        return match ($isCurrent) {
            true => $this->report($this->output, $current, self::SUCCEEDED),
            false => $this->report($this->errors, $stale, self::FAILED),
        };
    }

    private function rendered(): string
    {
        return $this->render($this->file->read($this->path(self::SOURCE)));
    }

    private function notGenerated(RuntimeException $exception): int
    {
        $message = sprintf(self::TEXT['not generated'], self::TARGET, $exception->getMessage());

        return $this->report($this->errors, "{$message}\n", self::FAILED);
    }

    private function path(string $relativePath): string
    {
        return "{$this->packageDir}/{$relativePath}";
    }

    private function report(mixed $stream, string $message, int $status): int
    {
        fwrite($stream, $message);

        return $status;
    }
}
