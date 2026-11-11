<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use RuntimeException;

final class PullRequestTemplates
{
    public const SHIPPED = '.github/pull_request_template.md';

    private const FOLDERS = ['', 'docs', '.github'];

    private const NAME = 'pull_request_template';

    private const EXTENSION = '.md';

    private string $root = '';
    private array $templates = [];
    private array $unreadable = [];

    public function __construct(private CheckedFile $file = new CheckedFile)
    {
    }

    public function inspect(string $projectDir): array
    {
        $this->root = rtrim($projectDir, '/');
        $this->templates = [];
        $this->unreadable = [];

        foreach (self::FOLDERS as $folder) {
            foreach ($this->entries($folder) as $path) {
                $this->visit($path);
            }
        }

        return ['templates' => $this->templates, 'unreadable' => $this->unreadable];
    }

    private function visit(string $path): void
    {
        $absolute = "{$this->root}/{$path}";
        $name = strtolower(basename($path));
        $file = self::NAME . self::EXTENSION;

        match (true) {
            is_link($absolute) => null,
            $name === self::NAME && is_dir($absolute) => $this->visitDirectory($path),
            $name === $file && is_file($absolute) => $this->templates[] = $path,
            default => null,
        };
    }

    private function visitDirectory(string $directory): void
    {
        $this->templates = [
            ...$this->templates,
            ...collect($this->entries($directory))
                ->filter(fn (string $path): bool => $this->isMarkdownFile($path))
                ->values()
                ->all(),
        ];
    }

    private function isMarkdownFile(string $path): bool
    {
        $absolute = "{$this->root}/{$path}";

        return str_ends_with(strtolower($path), self::EXTENSION)
            && is_file($absolute)
            && ! is_link($absolute);
    }

    private function entries(string $folder): array
    {
        $absolute = rtrim("{$this->root}/{$folder}", '/');
        $isSearchable = is_dir($absolute) && ($folder === '' || ! is_link($absolute));

        return match ($isSearchable) {
            true => $this->listed($folder, $absolute),
            false => [],
        };
    }

    private function listed(string $folder, string $absolute): array
    {
        try {
            $names = $this->file
                ->entries($absolute);
        } catch (RuntimeException $exception) {
            $shown = match ($folder) {
                '' => '.',
                default => $folder,
            };
            $this->unreadable[$shown] = $exception->getMessage();

            return [];
        }

        return collect($names)
            ->map(static fn (string $name): string => ltrim("{$folder}/{$name}", '/'))
            ->all();
    }
}
