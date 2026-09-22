<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
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
    public const OPENING_TAG = '<laravel-boost-guidelines>';

    public const CLOSING_TAG = '</laravel-boost-guidelines>';

    /**
     * @var list<string>
     */
    private const SKIP_DIRECTORIES = ['.git', 'node_modules', 'vendor'];

    /**
     * @var list<string>
     */
    private const EXTENSIONS = ['markdown', 'md', 'mdc'];

    /**
     * The project files composing would damage, each with the reason.
     *
     * @return array<string, string> relativePath => reason
     */
    public function hazards(string $projectDir): array
    {
        $hazards = [];

        foreach ($this->markdownFiles($projectDir) as $relativePath => $absolutePath) {
            $reason = $this->hazard($absolutePath);

            if ($reason !== null) {
                $hazards[$relativePath] = $reason;
            }
        }

        ksort($hazards);

        return $hazards;
    }

    /**
     * Why composing into this file would damage it, or null when it is safe.
     */
    private function hazard(string $absolutePath): ?string
    {
        $content = is_readable($absolutePath) ? @file_get_contents($absolutePath) : false;

        // Unreadable is treated as unsafe. A file this package cannot inspect
        // is one it cannot clear, and refusing costs a rerun where a wrong
        // "safe" costs the file.
        if ($content === false) {
            return 'could not be read, so this package cannot tell whether composing would damage it';
        }

        $openingTags = substr_count($content, self::OPENING_TAG);

        if ($openingTags === 0) {
            return null;
        }

        if ($openingTags > 1) {
            return sprintf(
                'holds %d "%s" tags, and composing replaces everything between the first tag and the next closing tag',
                $openingTags,
                self::OPENING_TAG,
            );
        }

        $opensAt = (int) strpos($content, self::OPENING_TAG);

        if (strpos($content, self::CLOSING_TAG, $opensAt) !== false) {
            return null;
        }

        return sprintf(
            'holds a "%s" tag with no closing tag after it, so composing appends a second block and the run after it replaces everything between the two',
            self::OPENING_TAG,
        );
    }

    /**
     * Every markdown file in the project, outside the skipped directories and
     * never through a symlink.
     *
     * @return array<string, string> relativePath => absolutePath
     */
    private function markdownFiles(string $projectDir): array
    {
        if (! is_dir($projectDir)) {
            return [];
        }

        $files = [];
        $prefixLength = strlen(rtrim($projectDir, '/')) + 1;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($projectDir, RecursiveDirectoryIterator::SKIP_DOTS),
                // Nothing is read through a symlink. An agent file linked into
                // vendor belongs to a dependency, not to this project, and
                // reporting it would name a path the developer cannot edit.
                // The iterator already declines to walk *into* a linked
                // directory; this check covers a linked file as well.
                fn (SplFileInfo $entry): bool => ! $entry->isLink()
                    && (! $entry->isDir() || ! in_array($entry->getFilename(), self::SKIP_DIRECTORIES, strict: true)),
            ),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $entry) {
            if (! in_array(strtolower($entry->getExtension()), self::EXTENSIONS, strict: true)) {
                continue;
            }

            $files[substr($entry->getPathname(), $prefixLength)] = $entry->getPathname();
        }

        return $files;
    }
}
