<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use RuntimeException;
use stdClass;

final class TestbenchMcp
{
    public const SERVER = 'laravel-boost';

    public const TESTBENCH = 'vendor/bin/testbench';

    private const ARGS = 'args';

    private const COMMAND = 'command';

    // Each JSON config Boost writes: the key that holds its servers, and the
    // entry field that holds the command's arguments.
    private const JSON_CONFIGS = [
        '.mcp.json' => ['mcpServers', self::ARGS],
        '.agents/mcp_config.json' => ['mcpServers', self::ARGS],
        '.amp/settings.json' => ['amp.mcpServers', self::ARGS],
        '.cursor/mcp.json' => ['mcpServers', self::ARGS],
        '.factory/mcp.json' => ['mcpServers', self::ARGS],
        '.junie/mcp/mcp.json' => ['mcpServers', self::ARGS],
        '.kiro/settings/mcp.json' => ['mcpServers', self::ARGS],
        '.vscode/mcp.json' => ['servers', self::ARGS],
        '.zed/settings.json' => ['context_servers', self::ARGS],
        'opencode.json' => ['mcp', self::COMMAND],
        'opencode.jsonc' => ['mcp', self::COMMAND],
    ];

    private const TOML_CONFIGS = [
        '.codex/config.toml',
        '.grok/config.toml',
    ];

    private const TOML_STRING = '"(?:\\\\.|[^"\\\\])*"';

    public function rewrite(string $projectDir): array
    {
        $result = ['rewritten' => [], 'skipped' => []];

        foreach ([...array_keys(self::JSON_CONFIGS), ...self::TOML_CONFIGS] as $relativePath) {
            $path = $projectDir . '/' . $relativePath;

            if (! is_file($path)) {
                continue;
            }

            try {
                $content = CheckedFile::read($path);

                if (! str_contains($content, self::SERVER)) {
                    continue;
                }

                if (! $this->isInside($projectDir, $path)) {
                    throw new RuntimeException('it resolves outside the project');
                }

                $rewritten = in_array($relativePath, self::TOML_CONFIGS, strict: true)
                    ? $this->rewriteToml(content: $content, projectDir: $projectDir)
                    : $this->rewriteJson($content, $projectDir, ...self::JSON_CONFIGS[$relativePath]);
            } catch (RuntimeException $exception) {
                $result['skipped'][$relativePath] = $exception->getMessage();

                continue;
            }

            if ($rewritten === null || $rewritten === $content) {
                continue;
            }

            try {
                CheckedFile::write($path, $rewritten);
                $result['rewritten'][] = $relativePath;
            } catch (RuntimeException $exception) {
                $result['skipped'][$relativePath] = $exception->getMessage();
            }
        }

        return $result;
    }

    private function rewriteJson(string $content, string $projectDir, string $key, string $list): ?string
    {
        $config = json_decode(trim($this->withoutBom($content)));

        if (! $config instanceof stdClass) {
            throw new RuntimeException('it is not plain JSON (comments or trailing commas), so this package cannot write it back unchanged');
        }

        $entry = $config->{$key}->{self::SERVER} ?? null;

        if (! $entry instanceof stdClass) {
            return null;
        }

        if (! is_array($entry->{$list} ?? null)) {
            throw new RuntimeException('its Boost entry has no ' . $list . ' list');
        }

        $rewritten = $this->throughTestbench($entry->{$list}, $projectDir);

        if ($rewritten === $entry->{$list}) {
            return null;
        }

        $entry->{$list} = $rewritten;

        return json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }

    private function rewriteToml(string $content, string $projectDir): ?string
    {
        $header = '/^\[mcp_servers\.(?:' . self::SERVER . '|"' . self::SERVER . '")\][ \t]*$/m';

        if (preg_match($header, $content, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        // Only the entry table's args array is replaced, so every other byte of
        // the file, comments included, is kept.
        $start = $match[0][1] + strlen($match[0][0]);
        $end = preg_match('/^[ \t]*\[/m', $content, $next, PREG_OFFSET_CAPTURE, $start) === 1 ? $next[0][1] : strlen($content);
        $table = substr($content, $start, $end - $start);
        $item = self::TOML_STRING;
        $line = "/^([ \\t]*args[ \\t]*=[ \\t]*)(\\[[ \\t]*(?:{$item}[ \\t]*(?:,[ \\t]*{$item}[ \\t]*)*,?[ \\t]*)?\\])[ \\t]*$/m";

        if (preg_match($line, $table, $args, PREG_OFFSET_CAPTURE) !== 1) {
            throw new RuntimeException('its Boost entry has no args line this package can read');
        }

        preg_match_all('/' . $item . '/', $args[2][0], $strings);

        $current = array_map(fn (string $string): string => stripcslashes(substr($string, 1, -1)), $strings[0]);
        $rewritten = $this->throughTestbench($current, $projectDir);

        if ($rewritten === $current) {
            return null;
        }

        $formatted = '[' . implode(', ', array_map(
            fn (string $arg): string => '"' . strtr($arg, ['\\' => '\\\\', '"' => '\\"', "\n" => '\\n', "\r" => '\\r', "\t" => '\\t']) . '"',
            $rewritten,
        )) . ']';

        return substr_replace($content, $formatted, $start + $args[2][1], strlen($args[2][0]));
    }

    private function throughTestbench(array $list, string $projectDir): array
    {
        $index = array_search('boost:mcp', $list, strict: true);
        $previous = is_int($index) && $index > 0 ? $list[$index - 1] : null;

        if (! is_string($previous)) {
            throw new RuntimeException('its Boost entry does not run boost:mcp through artisan');
        }

        if ($previous === self::TESTBENCH || str_ends_with($previous, '/' . self::TESTBENCH)) {
            return $list;
        }

        if (preg_match('~(?:\A|[/\\\\])artisan\z~', $previous) !== 1) {
            throw new RuntimeException('its Boost entry does not run boost:mcp through artisan');
        }

        $list[$index - 1] = $this->isAbsolute($previous) ? $projectDir . '/' . self::TESTBENCH : self::TESTBENCH;

        return $list;
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/\A[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    private function isInside(string $projectDir, string $path): bool
    {
        $root = realpath($projectDir);
        $resolved = realpath($path);

        return $root !== false && $resolved !== false && str_starts_with($resolved, $root . DIRECTORY_SEPARATOR);
    }

    private function withoutBom(string $content): string
    {
        return str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content;
    }
}
