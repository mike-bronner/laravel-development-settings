<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use Override;
use RuntimeException;

final class TomlMcpConfig implements McpConfig
{
    private const TABLE = '/^\s*\[\[?\s*(?<name>[^\[\]]+?)\s*\]\]?\s*(?:#.*)?$/';

    private const ARGS = '/^(?<opening>\s*args\s*=\s*\[)(?<arguments>.*)'
        . '(?<closing>\]\s*(?:#.*)?)$/';

    private const ENCODING = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    private const LINE = '/[^\n]*\n|[^\n]+\z/';

    private const STRING = "/\"(?:\\\\.|[^\"\\\\])*+\"|'[^']*'/";

    private const KEY = "[A-Za-z0-9_-]+|\"(?:\\\\.|[^\"\\\\])*+\"|'[^']*'";

    private const UNKNOWN_SHAPE = "holds a \"%s\" MCP server whose args name no artisan this"
        . ' package can replace';

    private bool $isInServer = false;
    private bool $hasServer = false;
    private bool $isRooted = false;

    public function __construct(private RootedTestbench $rooted)
    {
    }

    #[Override]
    public function mayBeConfig(string $prefix): bool
    {
        return true;
    }

    #[Override]
    public function rewrite(string $content): ?string
    {
        preg_match_all(self::LINE, $content, $lines);
        $lines = collect(data_get($lines, 0, []))
            ->map(fn (string $line): string => $this->line($line))
            ->implode('');

        return match (true) {
            ! $this->hasServer => null,
            ! $this->isRooted => throw new RuntimeException(
                sprintf(self::UNKNOWN_SHAPE, RootedTestbench::MCP_SERVER),
            ),
            default => $lines,
        };
    }

    private function line(string $line): string
    {
        $body = rtrim($line, "\r\n");
        $ending = substr($line, strlen($body));
        $isTable = preg_match(self::TABLE, $body, $table) === 1;

        $this->isInServer = match ($isTable) {
            true => $this->isServerTable(data_get($table, 'name')),
            false => $this->isInServer,
        };
        $this->hasServer = $this->hasServer || $this->isInServer;

        return match ($this->isInServer && preg_match(self::ARGS, $body, $args) === 1) {
            true => data_get($args, 'opening')
                . $this->arguments(data_get($args, 'arguments'))
                . data_get($args, 'closing')
                . $ending,
            false => $line,
        };
    }

    private function isServerTable(string $name): bool
    {
        $server = preg_quote(RootedTestbench::MCP_SERVER, '/');

        return preg_match("/^(?:" . self::KEY . ")\\s*\\.\\s*([\"']?){$server}\\1$/", $name) === 1;
    }

    private function arguments(string $arguments): string
    {
        return (string) preg_replace_callback(
                self::STRING,
                fn (array $match): string => $this->argument(data_get($match, [0])),
                $arguments,
            );
    }

    private function argument(string $text): string
    {
        $value = match (substr($text, 0, 1)) {
            "\"" => json_decode($text),
            default => substr($text, 1, -1),
        };
        $replacement = match (is_string($value)) {
            true => $this->rooted
                ->replacement($value),
            false => null,
        };
        $this->isRooted = $this->isRooted
            || $replacement !== null
            || (is_string($value) && $this->rooted->isScript($value));

        return match ($replacement) {
            null => $text,
            default => (string) json_encode($replacement, self::ENCODING),
        };
    }
}
