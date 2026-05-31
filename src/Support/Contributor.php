<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

/**
 * Opens a pull request on the development-settings repository containing local
 * edits made to symlinked sources (which live in vendor and are therefore
 * invisible to the CI upstream-sync that only sees committed files).
 *
 * Mirrors .github/workflows/reusable-sync.yml, but run locally and sourced
 * from the edited vendor files. All git/gh calls go through an injected
 * Process so the flow is testable without touching the network.
 */
final class Contributor
{
    public const REPO = 'mikebronner/development-settings';

    public function __construct(private Process $process) {}

    /**
     * @param  array<string, string>  $modified  relativePath => absolute source path
     * @return array{status: int, message: string}
     */
    public function open(array $modified, string $branch, string $cloneDir, ?string $token = null): array
    {
        if ($modified === []) {
            return ['status' => 0, 'message' => 'Nothing to contribute.'];
        }

        $url = $token !== null && $token !== ''
            ? 'https://x-access-token:' . $token . '@github.com/' . self::REPO . '.git'
            : 'https://github.com/' . self::REPO . '.git';

        if ($this->process->run('git clone --depth 1 ' . escapeshellarg($url) . ' ' . escapeshellarg($cloneDir)) !== 0) {
            return ['status' => 1, 'message' => 'Failed to clone ' . self::REPO . '.'];
        }

        try {
            if ($this->process->run('git checkout -b ' . escapeshellarg($branch), $cloneDir) !== 0) {
                return ['status' => 1, 'message' => 'Failed to create branch ' . $branch . '.'];
            }

            foreach ($modified as $relativePath => $absolutePath) {
                $this->copyInto($cloneDir, $relativePath, $absolutePath);
            }

            $this->process->run('git add -A', $cloneDir);

            // Nothing actually differs from upstream — treat as a no-op.
            if ($this->process->run('git diff --cached --quiet', $cloneDir) === 0) {
                return ['status' => 0, 'message' => 'No changes versus upstream — nothing to contribute.'];
            }

            if ($this->process->run('git commit -m ' . escapeshellarg('sync: contribute local development-settings edits'), $cloneDir) !== 0) {
                return ['status' => 1, 'message' => 'Failed to commit changes.'];
            }

            if ($this->process->run('git push -u origin ' . escapeshellarg($branch), $cloneDir) !== 0) {
                return ['status' => 1, 'message' => 'Failed to push branch ' . $branch . '.'];
            }

            $prCommand = 'gh pr create '
                . '--repo ' . escapeshellarg(self::REPO) . ' '
                . '--base main '
                . '--head ' . escapeshellarg($branch) . ' '
                . '--title ' . escapeshellarg('sync: contributed development-settings edits') . ' '
                . '--body ' . escapeshellarg($this->prBody($modified));

            if ($this->process->run($prCommand, $cloneDir) !== 0) {
                return ['status' => 1, 'message' => 'Pushed ' . $branch . ' but failed to open the PR (open it manually).'];
            }

            return ['status' => 0, 'message' => 'Opened a contribution PR from branch ' . $branch . '.'];
        } finally {
            $this->deleteTree($cloneDir);
        }
    }

    private function copyInto(string $cloneDir, string $relativePath, string $absolutePath): void
    {
        $destination = $cloneDir . '/' . $relativePath;
        $directory = dirname($destination);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        copy($absolutePath, $destination);
    }

    /**
     * @param  array<string, string>  $modified
     */
    private function prBody(array $modified): string
    {
        $files = implode("\n", array_map(static fn (string $path): string => '- `' . $path . '`', array_keys($modified)));

        return "## Contributed edits\n\nLocal edits to shared development-settings files:\n\n" . $files
            . "\n\nAfter merging, tag a new release to distribute these changes.";
    }

    private function deleteTree(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            $item->isDir() && ! $item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($path);
    }
}
