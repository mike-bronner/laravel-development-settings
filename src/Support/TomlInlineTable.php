<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

final class TomlInlineTable
{
    private const TOKEN = "/\"(?:\\\\.|[^\"\\\\])*\"|'[^']*'|[{}\\[\\]=,]|[A-Za-z0-9_-]+/";

    private array $frames = [];
    private array $keys = [];
    private ?array $valuePath = null;
    private array $paths = [];

    public function paths(array $assigned, string $value): array
    {
        preg_match_all(self::TOKEN, $value, $matches);
        $this->frames = [];
        $this->keys = [];
        $this->valuePath = $assigned;
        $this->paths = [];

        collect(data_get($matches, 0, []))
            ->each(fn (string $token): mixed => $this->accept($token));

        return $this->paths;
    }

    private function accept(string $token): void
    {
        match ($token) {
            '{' => $this->open($this->valuePath),
            '[' => $this->open(null),
            '}', ']' => array_pop($this->frames),
            '=' => $this->assign(),
            ',' => $this->separate(),
            default => $this->word($token),
        };
    }

    private function open(?array $path): void
    {
        $this->frames[] = $path;
        $this->keys = [];
        $this->valuePath = null;
    }

    private function separate(): void
    {
        $this->keys = [];
        $this->valuePath = null;
    }

    private function assign(): void
    {
        $table = end($this->frames);
        $this->valuePath = match (is_array($table)) {
            true => [...$table, ...$this->keys],
            false => null,
        };
        $this->keys = [];

        match ($this->valuePath) {
            null => null,
            default => $this->paths[] = $this->valuePath,
        };
    }

    private function word(string $token): void
    {
        match (is_array(end($this->frames))) {
            true => $this->keys[] = match (substr($token, 0, 1)) {
                "\"" => (string) json_decode($token),
                "'" => substr($token, 1, -1),
                default => $token,
            },
            false => null,
        };
    }
}
