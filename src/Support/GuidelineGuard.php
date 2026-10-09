<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class GuidelineGuard
{
    public const OPENING_TAG = <<<TAG
        <laravel-boost-guidelines>
        TAG;

    public const CLOSING_TAG = <<<TAG
        </laravel-boost-guidelines>
        TAG;

    private const SKIP_DIRECTORIES = ['.git', 'node_modules', 'vendor'];

    private const EXTENSIONS = ['markdown', 'md', 'mdc'];

    private const UNREADABLE = 'could not be read, so this package cannot tell whether composing'
        . ' would damage it';

    private const REPEATED = "holds %d \"%s\" tags, and composing replaces everything"
        . ' between the first tag and the next closing tag';

    private const UNCLOSED = "holds a \"%s\" tag with no closing tag after it, so composing"
        . ' appends a second block and the run after it replaces everything between the two';

    public function __construct(private CheckedFile $file = new CheckedFile)
    {
    }

    public function hazards(string $projectDir): array
    {
        return collect($this->markdownFiles($projectDir))
            ->map(fn (string $absolutePath): ?string => $this->hazard($absolutePath))
            ->whereNotNull()
            ->sortKeys()
            ->all();
    }

    public function composedBlocks(string $projectDir, int $since): array
    {
        clearstatcache();

        return collect($this->markdownFiles($projectDir))
            ->map(fn (string $absolutePath): ?string => $this->composedAt($absolutePath, $since))
            ->whereNotNull()
            ->sortKeys()
            ->all();
    }

    private function composedAt(string $absolutePath, int $since): ?string
    {
        try {
            $modifiedAt = (new SplFileInfo($absolutePath))->getMTime();
            $content = $this->file
                ->read($absolutePath);
        } catch (RuntimeException) {
            return null;
        }

        return match ($modifiedAt < $since) {
            true => null,
            false => $this->block($content),
        };
    }

    private function hazard(string $absolutePath): ?string
    {
        try {
            $content = $this->file
                ->read($absolutePath);
        } catch (RuntimeException) {
            return self::UNREADABLE;
        }

        $openingTags = substr_count($content, self::OPENING_TAG);

        return match (true) {
            $openingTags === 0 => null,
            $openingTags > 1 => sprintf(self::REPEATED, $openingTags, self::OPENING_TAG),
            is_string($this->block($content)) => null,
            default => sprintf(self::UNCLOSED, self::OPENING_TAG),
        };
    }

    private function block(string $content): ?string
    {
        $opensAt = strpos($content, self::OPENING_TAG);
        $closesAt = match ($opensAt) {
            false => false,
            default => strpos($content, self::CLOSING_TAG, $opensAt),
        };

        return match ($closesAt) {
            false => null,
            default => substr($content, (int) $opensAt, $closesAt - (int) $opensAt),
        };
    }

    private function markdownFiles(string $projectDir): array
    {
        return match (is_dir($projectDir)) {
            true => $this->markdownFilesIn($projectDir),
            false => [],
        };
    }

    private function markdownFilesIn(string $projectDir): array
    {
        $prefixLength = strlen(rtrim($projectDir, '/')) + 1;
        $iterator = new RecursiveIteratorIterator(
                new RecursiveCallbackFilterIterator(
                        new RecursiveDirectoryIterator(
                                $projectDir,
                                RecursiveDirectoryIterator::SKIP_DOTS,
                            ),
                        fn (SplFileInfo $entry): bool => $this->isExamined($entry),
                    ),
                RecursiveIteratorIterator::LEAVES_ONLY,
            );

        return collect(iterator_to_array($iterator))
            ->filter(fn (SplFileInfo $entry): bool => $this->isMarkdown($entry))
            ->mapWithKeys(fn (SplFileInfo $entry): array => $this->relative($entry, $prefixLength))
            ->all();
    }

    private function isExamined(SplFileInfo $entry): bool
    {
        $isSkippedDirectory = $entry->isDir()
            && in_array($entry->getFilename(), self::SKIP_DIRECTORIES, strict: true);

        return ! $entry->isLink() && ! $isSkippedDirectory;
    }

    private function isMarkdown(SplFileInfo $entry): bool
    {
        return in_array(strtolower($entry->getExtension()), self::EXTENSIONS, strict: true);
    }

    private function relative(SplFileInfo $entry, int $prefixLength): array
    {
        $pathname = $entry->getPathname();

        return [substr($pathname, $prefixLength) => $pathname];
    }
}
