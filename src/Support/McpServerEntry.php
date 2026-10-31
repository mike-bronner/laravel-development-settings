<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

final class McpServerEntry
{
    private const BYTE_ORDER_MARK = "\xEF\xBB\xBF";

    private const STRING = "\"(?:\\\\.|[^\"\\\\])*+\"";

    private const COMMENT = '\\/\\/[^\\n]*|\\/\\*.*?\\*\\/';

    private const TRAILING_COMMA = ',(?=\\s*[}\\]])';

    private const TOML_KEY = "[A-Za-z0-9_-]+|\"(?:\\\\.|[^\"\\\\])*\"|'[^']*'";

    private const TOML_KEYS = '(?<keys>(?:' . self::TOML_KEY . ')'
        . '(?:\\s*\\.\\s*(?:' . self::TOML_KEY . '))*)';

    private const TOML_HEADER = '/^\\s*\\[\\[?\\s*' . self::TOML_KEYS . '\\s*\\]\\]?\\s*(?:#.*)?$/';

    private const TOML_ASSIGNMENT = '/^\\s*' . self::TOML_KEYS . '\\s*=(?<value>.*)$/';

    public function exists(string $path, string $configKey, string $server): bool
    {
        $content = match (is_file($path)) {
            true => (string) file_get_contents($path),
            false => '',
        };
        $content = trim((string) preg_replace('/\A' . self::BYTE_ORDER_MARK . '/', '', $content));

        return match (str_ends_with($path, '.toml')) {
            true => $this->isTomlTable($content, $configKey, $server),
            false => $this->isJsonMember($content, $configKey, $server),
        };
    }

    private function isTomlTable(string $content, string $configKey, string $server): bool
    {
        $target = [...explode('.', $configKey), $server];
        $lines = explode("\n", str_replace("\r", '', $content));
        [$isFound] = collect($lines)->reduce(
                fn (array $state, string $line): array => $this->tomlStep($state, $line, $target),
                [false, []],
            );

        return $isFound;
    }

    private function tomlStep(array $state, string $line, array $target): array
    {
        [$isFound, $table] = $state;
        $isHeader = preg_match(self::TOML_HEADER, $line, $header) === 1;
        $isAssignment = preg_match(self::TOML_ASSIGNMENT, $line, $assignment) === 1;
        $headerKeys = $this->tomlKeys((string) data_get($header, 'keys', ''));
        $assignedKeys = $this->tomlKeys((string) data_get($assignment, 'keys', ''));
        $assignedPath = [...$table, ...$assignedKeys];
        $value = (string) data_get($assignment, 'value');
        [$table, $paths] = match (true) {
            $isHeader => [$headerKeys, [$headerKeys]],
            $isAssignment => [$table, [
                $assignedPath,
                ...(new TomlInlineTable)->paths($assignedPath, $value),
            ]],
            default => [$table, []],
        };
        $isTarget = fn (array $path): bool => array_slice($path, 0, count($target)) === $target;

        return [$isFound || collect($paths)->contains($isTarget), $table];
    }

    private function tomlKeys(string $keys): array
    {
        preg_match_all('/' . self::TOML_KEY . '/', $keys, $matches);

        return collect(data_get($matches, 0, []))
            ->map(fn (string $key): string => match (substr($key, 0, 1)) {
                "\"" => (string) json_decode($key),
                "'" => substr($key, 1, -1),
                default => $key,
            })
            ->all();
    }

    private function isJsonMember(string $content, string $configKey, string $server): bool
    {
        $config = json_decode($this->withoutComments($content));
        $servers = match (is_object($config)) {
            true => $config->{$configKey} ?? null,
            false => null,
        };

        return is_object($servers) && property_exists($servers, $server);
    }

    private function withoutComments(string $content): string
    {
        return $this->strip(self::TRAILING_COMMA, $this->strip(self::COMMENT, $content));
    }

    private function strip(string $pattern, string $content): string
    {
        return (string) preg_replace_callback(
                '/' . self::STRING . "|{$pattern}/s",
                fn (array $match): string => match (str_starts_with(data_get($match, [0]), "\"")) {
                    true => data_get($match, [0]),
                    false => '',
                },
                $content,
            );
    }
}
