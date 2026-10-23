<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use RuntimeException;
use SplFileInfo;

final class McpConfigRewriter
{
    public const MAX_BYTES = 262_144;

    private const PREFIX_BYTES = 4_096;

    private const LEFT_ALONE = false;

    public function __construct(private CheckedFile $file = new CheckedFile)
    {
    }

    public function rewrite(string $projectDir, int $since): array
    {
        clearstatcache();
        $rooted = new RootedTestbench($projectDir);
        $scan = (new ProjectFiles)->scan($projectDir);
        [$unread, $configs] = collect(data_get($scan, 'files'))
            ->filter(fn (string $path): bool => $this->isCandidate($path, $since))
            ->map(fn (string $path): RuntimeException|string|false|null => $this->settle(
                    $path,
                    $rooted,
                ))
            ->reject(fn (mixed $outcome): bool => $outcome === self::LEFT_ALONE)
            ->sortKeys()
            ->partition(fn (mixed $outcome): bool => $outcome instanceof RuntimeException);

        return [
            'configs' => $configs->all(),
            'unread' => $unread
                ->map(fn (RuntimeException $failure): string => $failure->getMessage())
                ->union(data_get($scan, 'unreadable'))
                ->sortKeys()
                ->all(),
        ];
    }

    private function isCandidate(string $absolutePath, int $since): bool
    {
        try {
            $file = new SplFileInfo($absolutePath);

            return $file->getMTime() >= $since && $file->getSize() <= self::MAX_BYTES;
        } catch (RuntimeException) {
            return false;
        }
    }

    private function settle(
        string $absolutePath,
        RootedTestbench $rooted,
    ): RuntimeException|string|false|null {
        $config = match (str_ends_with(strtolower($absolutePath), '.toml')) {
            true => new TomlMcpConfig($rooted),
            false => new JsonMcpConfig($rooted),
        };

        try {
            $prefix = $this->file
                ->readPrefix($absolutePath, self::PREFIX_BYTES);
        } catch (RuntimeException $exception) {
            return $exception;
        }

        return match ($config->mayBeConfig($prefix)) {
            true => $this->rewritten($absolutePath, $config),
            false => self::LEFT_ALONE,
        };
    }

    private function rewritten(
        string $absolutePath,
        McpConfig $config,
    ): string|false|null {
        try {
            $content = $this->file
                ->read($absolutePath);
            $rewritten = $config->rewrite($content);
            $isChanged = is_string($rewritten) && $rewritten !== $content;
            $isChanged && $this->file
                ->write($absolutePath, (string) $rewritten);
        } catch (RuntimeException $exception) {
            return $exception->getMessage();
        }

        return match ($isChanged) {
            true => null,
            false => self::LEFT_ALONE,
        };
    }
}
