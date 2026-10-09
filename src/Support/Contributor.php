<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use Closure;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

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

    public function branchFor(string $projectDir): string
    {
        $slug = preg_replace('/[^a-z0-9._-]+/i', '-', basename($projectDir)) ?? 'project';
        $time = date('YmdHis');

        return "contribute/{$slug}-{$time}";
    }

    public function cloneDirectory(): string
    {
        $suffix = bin2hex(random_bytes(self::CLONE_SUFFIX_BYTES));

        return sys_get_temp_dir() . "/devset-contribute-{$suffix}";
    }

    private function url(string $token): string
    {
        return match ($token) {
            '' => 'https://github.com/' . self::REPO . '.git',
            default => "https://x-access-token:{$token}@github.com/" . self::REPO . '.git',
        };
    }

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

    private function firstOutcome(array $steps): ?array
    {
        return collect($steps)
            ->reduce(static fn (?array $outcome, Closure $step): ?array => $outcome ?? $step());
    }

    private function failureOf(string $command, ?string $workingDirectory, string $failure): ?array
    {
        $process = $this->process;

        return match ($process->run($command, $workingDirectory)) {
            self::SUCCEEDED => null,
            default => $this->result(self::FAILED, $failure),
        };
    }

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

    private function pullRequestCommand(array $modified, string $branch): string
    {
        $repository = escapeshellarg(self::REPO);
        $head = escapeshellarg($branch);
        $title = escapeshellarg(self::PR_TITLE);
        $body = escapeshellarg($this->prBody($modified));

        return "gh pr create --repo {$repository} --base main --head {$head}"
            . " --title {$title} --body {$body}";
    }

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
