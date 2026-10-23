<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

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
        return data_get($this->inspect($projectDir), 'hazards');
    }

    public function inspect(string $projectDir): array
    {
        $scan = (new ProjectFiles)->scan($projectDir);
        $hazards = collect($this->markdownFiles(data_get($scan, 'files')))
            ->map(fn (string $absolutePath): ?string => $this->hazard($absolutePath))
            ->whereNotNull()
            ->sortKeys()
            ->all();

        return ['hazards' => $hazards, 'unopened' => data_get($scan, 'unreadable')];
    }

    public function composedBlocks(string $projectDir, int $since): array
    {
        clearstatcache();

        $files = data_get((new ProjectFiles)->scan($projectDir), 'files');

        return collect($this->markdownFiles($files))
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

    private function markdownFiles(array $files): array
    {
        return collect($files)
            ->filter(fn (string $absolutePath): bool => $this->isMarkdown($absolutePath))
            ->all();
    }

    private function isMarkdown(string $absolutePath): bool
    {
        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

        return in_array($extension, self::EXTENSIONS, strict: true);
    }
}
