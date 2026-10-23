<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use RuntimeException;

final class ProjectFiles
{
    private const SKIP_DIRECTORIES = ['.git', 'node_modules', 'vendor'];

    private string $root = '';
    private array $files = [];
    private array $unreadable = [];

    public function __construct(private CheckedFile $file = new CheckedFile)
    {
    }

    public function scan(string $projectDir): array
    {
        $this->root = rtrim($projectDir, '/');
        $this->files = [];
        $this->unreadable = [];

        match (is_dir($projectDir)) {
            true => $this->walk($this->root),
            false => null,
        };

        return ['files' => $this->files, 'unreadable' => $this->unreadable];
    }

    private function walk(string $directory): void
    {
        try {
            $names = $this->file
                ->entries($directory);
        } catch (RuntimeException $exception) {
            $this->unreadable[$this->relative($directory)] = $exception->getMessage();

            return;
        }

        foreach ($names as $name) {
            $this->visit("{$directory}/{$name}");
        }
    }

    private function visit(string $path): void
    {
        $isSkippedDirectory = in_array(basename($path), self::SKIP_DIRECTORIES, strict: true)
            && is_dir($path);

        match (true) {
            is_link($path), $isSkippedDirectory => null,
            is_dir($path) => $this->walk($path),
            default => $this->files[$this->relative($path)] = $path,
        };
    }

    private function relative(string $path): string
    {
        return match ($path) {
            $this->root => '.',
            default => substr($path, strlen($this->root) + 1),
        };
    }
}
