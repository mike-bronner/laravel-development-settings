<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

final class ContributeCommand
{
    private const NOTHING = "No local development-settings edits to contribute.\n";

    private const CONTRIBUTING = "Contributing %d edited file(s) to %s:\n";

    private const SUCCEEDED = 0;

    public function __construct(
        private string $projectDir,
        private string $packageDir,
        private mixed $output,
        private mixed $errors,
        private Process $process = new SystemProcess,
    ) {
    }

    public function run(): int
    {
        $config = (new InstalledPackage($this->projectDir))->config($this->packageDir);
        $manifestPath = "{$this->packageDir}/" . ContributionDetector::MANIFEST_FILE;
        $modified = (new ContributionDetector)->modified(
                packageDir: $this->packageDir,
                directories: $config->entries(PackageConfig::CAPTURE),
                sources: (new ManifestReader)->read($manifestPath),
                ignore: $config->entries(PackageConfig::IGNORE),
            );

        return match ($modified) {
            [] => $this->nothingToContribute(),
            default => $this->contribute($modified),
        };
    }

    private function nothingToContribute(): int
    {
        fwrite($this->output, self::NOTHING);

        return self::SUCCEEDED;
    }

    private function contribute(array $modified): int
    {
        $contributor = new Contributor($this->process);

        fwrite($this->output, sprintf(self::CONTRIBUTING, count($modified), Contributor::REPO));

        foreach (array_keys($modified) as $path) {
            fwrite($this->output, <<<LINE
                  - {$path}

                LINE);
        }

        ['status' => $status, 'message' => $message] = $contributor->open(
                modified: $modified,
                branch: $contributor->branchFor($this->projectDir),
                cloneDir: $contributor->cloneDirectory(),
                token: (string) getenv('DEVELOPER_SETTINGS_TOKEN'),
            );

        $stream = match ($status) {
            self::SUCCEEDED => $this->output,
            default => $this->errors,
        };
        fwrite($stream, "{$message}\n");

        return $status;
    }
}
