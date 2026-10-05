<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Finds agent files that Laravel Boost would damage if it composed into them.
 *
 * Boost writes its composed guidelines by replacing the region its opening and
 * closing marker tags delimit. The pattern is non-greedy, spans newlines, and
 * anchors on the *first* opening tag anywhere in the file
 * (`Install\GuidelineWriter::write()`). So a hand-written section that names the
 * opening tag in prose becomes the start of the match, the real block's closing
 * tag becomes its end, and every line between the two is overwritten by
 * generated content. This is an upstream defect, it is present in the version
 * this package requires, and it has already cut one consuming project's agent
 * file from 299 lines to 125.
 *
 * The file is never repaired, only reported. Where a hand-written section ends
 * cannot be derived from the text, and a wrong guess destroys the same content
 * the guard exists to save.
 *
 * ## What counts as damage
 *
 * - **Two or more opening tags.** The next composition replaces everything from
 *   the first tag to the nearest closing tag after it.
 * - **One opening tag with no closing tag after it.** This run composes
 *   cleanly and appends a real block, which leaves two opening tags behind. The
 *   run looks successful and arms the next one, so it is refused here rather
 *   than one composition later.
 *
 * One opening tag with a closing tag after it is the managed shape, and it
 * composes untouched. A file with no opening tag is composed into by appending,
 * which destroys nothing.
 *
 * ## Which files are examined
 *
 * Every markdown file in the project, minus `vendor`, `node_modules`, `.git`
 * and anything reached through a symlink. Boost's own map of agents to
 * guideline paths is deliberately not copied: it lives upstream, every entry is
 * overridable through `config('boost.agents.*.guidelines_path')`, and a copy
 * here would rot between releases without a single failing test. Examining a
 * file Boost never writes to costs one read; missing one it does write to costs
 * the whole guard.
 */
final class GuidelineGuard
{
    public const OPENING_TAG = <<<TAG
        <laravel-boost-guidelines>
        TAG;

    public const CLOSING_TAG = <<<TAG
        </laravel-boost-guidelines>
        TAG;

    /**
     * @var list<string>
     */
    private const SKIP_DIRECTORIES = ['.git', 'node_modules', 'vendor'];

    /**
     * @var list<string>
     */
    private const EXTENSIONS = ['markdown', 'md', 'mdc'];

    private const UNREADABLE = 'could not be read, so this package cannot tell whether composing'
        . ' would damage it';

    private const REPEATED = "holds %d \"%s\" tags, and composing replaces everything"
        . ' between the first tag and the next closing tag';

    private const UNCLOSED = "holds a \"%s\" tag with no closing tag after it, so composing"
        . ' appends a second block and the run after it replaces everything between the two';

    public function __construct(private CheckedFile $file = new CheckedFile())
    {
    }

    /**
     * The project files composing would damage, each with the reason.
     *
     * @return array<string, string> relativePath => reason
     */
    public function hazards(string $projectDir): array
    {
        return collect($this->markdownFiles($projectDir))
            ->map(fn (string $absolutePath): ?string => $this->hazard($absolutePath))
            ->whereNotNull()
            ->sortKeys()
            ->all();
    }

    /**
     * The composed Boost block of each project file written at or after
     * `$since`, keyed on its relative path, in path order. A block runs from
     * the first opening tag to the closing tag after it.
     *
     * Boost exits successfully when it finds no agent to compose for, so its
     * exit code cannot tell a composition from a run that wrote nothing. The
     * file on disk can. Boost rewrites every agent file it composes into, even
     * when the content is unchanged, so a block written before the run began
     * (the Laravel skeleton ships one) does not count. Every agent Boost
     * supports writes its guidelines to a markdown file, which is the set this
     * class already reads.
     */
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

    /**
     * Why composing into this file would damage it, or null when it is safe.
     *
     * Unreadable is treated as unsafe. A file this package cannot inspect is
     * one it cannot clear, and refusing costs a rerun where a wrong "safe"
     * costs the file.
     */
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

    /**
     * Every markdown file in the project, outside the skipped directories and
     * never through a symlink.
     *
     * @return array<string, string> relativePath => absolutePath
     */
    private function markdownFiles(string $projectDir): array
    {
        return match (is_dir($projectDir)) {
            true => $this->markdownFilesIn($projectDir),
            false => [],
        };
    }

    /**
     * @return array<string, string> relativePath => absolutePath
     */
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

    /**
     * Nothing is read through a symlink. An agent file linked into vendor
     * belongs to a dependency, not to this project, and reporting it would
     * name a path the developer cannot edit. The iterator already declines to
     * walk *into* a linked directory; this check covers a linked file as well.
     */
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

    /**
     * @return array<string, string> relativePath => absolutePath
     */
    private function relative(SplFileInfo $entry, int $prefixLength): array
    {
        $pathname = $entry->getPathname();

        return [substr($pathname, $prefixLength) => $pathname];
    }
}
