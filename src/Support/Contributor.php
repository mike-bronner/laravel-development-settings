<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use Closure;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Opens a pull request on the development-settings repository containing local
 * edits made to this package's installed guideline and skill sources. Those
 * live in vendor, so no commit in the consuming project ever carries them.
 *
 * Each path is package-relative (`resources/boost/…`), which is where the file
 * lives in the upstream repository too. All git/gh calls go through an
 * injected Process so the flow is testable without touching the network.
 *
 * The flow is a list of steps run in order. A step answers null to let the
 * next one run, or the result that ends the flow: a failure with its own
 * message, or the no-op when nothing differs from upstream.
 *
 * @phpstan-type Result array{status: int, message: string}
 */
final class Contributor
{
    public const REPO = 'mike-bronner/laravel-development-settings';

    private const SUCCEEDED = 0;

    private const FAILED = 1;

    private const DIRECTORY_PERMISSIONS = 0755;

    private const CLONE_SUFFIX_BYTES = 5;

    private const COMMIT_MESSAGE = 'sync: contribute local development-settings edits';

    private const PR_TITLE = 'sync: contributed development-settings edits';

    public function __construct(private Process $process)
    {
    }

    /**
     * @param  array<string, string>  $modified  relativePath => absolute source path
     * @return Result
     */
    public function open(
        array $modified,
        string $branch,
        string $cloneDir,
        ?string $token = null,
    ): array {
        $url = escapeshellarg($this->url((string) $token));
        $directory = escapeshellarg($cloneDir);
        $cloneFailed = fn (): ?array => $this->failureOf(
                "git clone --depth 1 {$url} {$directory}",
                null,
                'Failed to clone ' . self::REPO . '.',
            );

        return match ($modified) {
            [] => $this->result(self::SUCCEEDED, 'Nothing to contribute.'),
            default => $cloneFailed() ?? $this->contributeFrom($modified, $branch, $cloneDir),
        };
    }

    /**
     * The branch a contribution from this project is pushed to: the project
     * directory's name, made safe for a ref, and the time.
     */
    public function branchFor(string $projectDir): string
    {
        $slug = preg_replace('/[^a-z0-9._-]+/i', '-', basename($projectDir)) ?? 'project';
        $time = date('YmdHis');

        return "contribute/{$slug}-{$time}";
    }

    /**
     * A fresh temporary directory to clone into.
     */
    public function cloneDirectory(): string
    {
        $suffix = bin2hex(random_bytes(self::CLONE_SUFFIX_BYTES));

        return sys_get_temp_dir() . "/devset-contribute-{$suffix}";
    }

    /**
     * An empty token clones anonymously, as no token does.
     */
    private function url(string $token): string
    {
        return match ($token) {
            '' => 'https://github.com/' . self::REPO . '.git',
            default => "https://x-access-token:{$token}@github.com/" . self::REPO . '.git',
        };
    }

    /**
     * Run the rest of the flow in the clone, and delete the clone whatever the
     * outcome.
     *
     * @param  array<string, string>  $modified
     * @return Result
     */
    private function contributeFrom(array $modified, string $branch, string $cloneDir): array
    {
        $quotedBranch = escapeshellarg($branch);
        $message = escapeshellarg(self::COMMIT_MESSAGE);
        $opened = $this->result(self::SUCCEEDED, "Opened a contribution PR from branch {$branch}.");

        try {
            return $this->firstOutcome([
                fn (): ?array => $this->failureOf(
                        "git checkout -b {$quotedBranch}",
                        $cloneDir,
                        "Failed to create branch {$branch}.",
                    ),
                fn (): ?array => $this->stage($modified, $cloneDir),
                fn (): ?array => $this->failureOf(
                        "git commit -m {$message}",
                        $cloneDir,
                        'Failed to commit changes.',
                    ),
                fn (): ?array => $this->failureOf(
                        "git push -u origin {$quotedBranch}",
                        $cloneDir,
                        "Failed to push branch {$branch}.",
                    ),
                fn (): ?array => $this->failureOf(
                        $this->pullRequestCommand($modified, $branch),
                        $cloneDir,
                        "Pushed {$branch} but failed to open the PR (open it manually).",
                    ),
            ]) ?? $opened;
        } finally {
            $this->deleteTree($cloneDir);
        }
    }

    /**
     * The result of the first step that ends the flow, or null when every step
     * lets it carry on. A step after the one that ends it never runs.
     *
     * @param  list<Closure(): (Result|null)>  $steps
     * @return Result|null
     */
    private function firstOutcome(array $steps): ?array
    {
        return collect($steps)
            ->reduce(static fn (?array $outcome, Closure $step): ?array => $outcome ?? $step());
    }

    /**
     * Run one command, and answer the failure when it does not succeed.
     *
     * @return Result|null
     */
    private function failureOf(string $command, ?string $workingDirectory, string $failure): ?array
    {
        $process = $this->process;

        return match ($process->run($command, $workingDirectory)) {
            self::SUCCEEDED => null,
            default => $this->result(self::FAILED, $failure),
        };
    }

    /**
     * Copy the edits into the clone and stage them. Nothing that differs from
     * upstream ends the flow as a no-op, not a failure.
     *
     * @param  array<string, string>  $modified
     * @return Result|null
     */
    private function stage(array $modified, string $cloneDir): ?array
    {
        foreach ($modified as $relativePath => $absolutePath) {
            $this->copyInto($cloneDir, $relativePath, $absolutePath);
        }

        $process = $this->process;
        $process->run('git add -A', $cloneDir);

        return match ($process->run('git diff --cached --quiet', $cloneDir)) {
            self::SUCCEEDED => $this->result(
                self::SUCCEEDED,
                'No changes versus upstream — nothing to contribute.',
            ),
            default => null,
        };
    }

    /**
     * @param  array<string, string>  $modified
     */
    private function pullRequestCommand(array $modified, string $branch): string
    {
        $repository = escapeshellarg(self::REPO);
        $head = escapeshellarg($branch);
        $title = escapeshellarg(self::PR_TITLE);
        $body = escapeshellarg($this->prBody($modified));

        return "gh pr create --repo {$repository} --base main --head {$head}"
            . " --title {$title} --body {$body}";
    }

    /**
     * @return Result
     */
    private function result(int $status, string $message): array
    {
        return ['status' => $status, 'message' => $message];
    }

    private function copyInto(string $cloneDir, string $relativePath, string $absolutePath): void
    {
        $destination = "{$cloneDir}/{$relativePath}";
        $directory = dirname($destination);

        is_dir($directory) || mkdir($directory, self::DIRECTORY_PERMISSIONS, recursive: true);
        copy($absolutePath, $destination);
    }

    /**
     * @param  array<string, string>  $modified
     */
    private function prBody(array $modified): string
    {
        $files = collect(array_keys($modified))
            ->map(static fn (string $path): string => <<<ITEM
                - `{$path}`
                ITEM)
            ->implode("\n");

        return <<<MARKDOWN
            ## Contributed edits

            Local edits to shared development-settings files:

            {$files}

            After merging, tag a new release to distribute these changes.
            MARKDOWN;
    }

    private function deleteTree(string $path): void
    {
        match (is_dir($path)) {
            true => $this->deleteDirectory($path),
            false => null,
        };
    }

    private function deleteDirectory(string $path): void
    {
        $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );

        foreach ($iterator as $item) {
            $this->deleteEntry($item);
        }

        rmdir($path);
    }

    private function deleteEntry(SplFileInfo $item): void
    {
        $pathname = $item->getPathname();

        match ($item->isDir() && ! $item->isLink()) {
            true => rmdir($pathname),
            false => unlink($pathname),
        };
    }
}
