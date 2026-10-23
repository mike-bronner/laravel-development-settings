<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use Override;
use RuntimeException;

final class JsonMcpConfig implements McpConfig
{
    private const TOKENS = [
        '\s+',
        '\/\/[^\n]*',
        '\/\*.*?\*\/',
        "\"(?:\\\\.|[^\"\\\\])*+\"",
        "'(?:\\\\.|[^'\\\\])*+'",
        '[{}\[\]:,]',
        "[^\\s{}\\[\\]:,\"'\\/]+",
        '(?<invalid>.)',
    ];

    private const START = '/\A(?:\s++|\/\/[^\n]*+|\/\*(?:.*?\*\/|.*+\z))*+(?<first>.)?/s';

    private const ENCODING = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    private const COMMAND_KEYS = ['args', 'command'];

    private const SERVER_DEPTH = 2;

    private const ARGUMENT_DEPTH = 4;

    private const UNKNOWN_SHAPE = "holds a \"%s\" MCP server whose command names no artisan this"
        . ' package can replace';

    private array $stack = [];
    private bool $isClosed = false;
    private bool $isValid = true;
    private bool $hasServer = false;
    private array $replacements = [];
    private bool $isRooted = false;

    public function __construct(private RootedTestbench $rooted)
    {
    }

    #[Override]
    public function mayBeConfig(string $prefix): bool
    {
        preg_match(self::START, $prefix, $start);

        return in_array(data_get($start, 'first'), [null, '{'], strict: true);
    }

    #[Override]
    public function rewrite(string $content): ?string
    {
        $tokens = $this->tokens($content);

        foreach ($tokens ?? [] as [$text, $offset]) {
            $this->isValid = $this->isValid && $this->accept($text, $offset);
        }

        return match (true) {
            $tokens === null,
            ! $this->isValid,
            ! $this->isClosed,
            ! $this->hasServer => null,
            ! $this->isRooted => throw new RuntimeException(
                sprintf(self::UNKNOWN_SHAPE, RootedTestbench::MCP_SERVER),
            ),
            default => $this->replaced($content),
        };
    }

    private function tokens(string $content): ?array
    {
        $pattern = '/' . implode('|', self::TOKENS) . '/s';
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $tokens = collect($matches)
            ->reject(fn (array $match): bool => $this->isSkipped(data_get($match, [0, 0])))
            ->map(fn (array $match): array => data_get($match, [0]))
            ->values();
        $hasInvalid = collect($matches)
            ->contains(fn (array $match): bool => (data_get($match, ['invalid', 1]) ?? -1) >= 0);

        return match ($hasInvalid) {
            true => null,
            false => $tokens->all(),
        };
    }

    private function isSkipped(string $text): bool
    {
        return trim($text) === '' || str_starts_with($text, '//') || str_starts_with($text, '/*');
    }

    private function accept(string $text, int $offset): bool
    {
        return match (true) {
            $this->isClosed => false,
            $this->stack === [] => $text === '{' && $this->open($text),
            in_array($text, ['{', '['], strict: true) => $this->open($text),
            in_array($text, ['}', ']'], strict: true) => $this->close($text),
            $text === ':' => $this->separateValue(),
            $text === ',' => $this->separateMember(),
            default => $this->scalar($text, $offset),
        };
    }

    private function open(string $bracket): true
    {
        $this->hasServer = $this->hasServer || ($bracket === '{' && $this->isAtServer());
        $this->stack[] = ['bracket' => $bracket, 'key' => null, 'expectsKey' => $bracket === '{'];

        return true;
    }

    private function isAtServer(): bool
    {
        return count($this->stack) === self::SERVER_DEPTH
            && $this->keyAt(self::SERVER_DEPTH - 1) === RootedTestbench::MCP_SERVER;
    }

    private function close(string $bracket): bool
    {
        $opening = array_pop($this->stack)['bracket'];
        $this->isClosed = $this->stack === [];

        return ['{' => '}', '[' => ']'][$opening] === $bracket;
    }

    private function separateValue(): bool
    {
        $this->setTop('expectsKey', false);

        return $this->topBracket() === '{';
    }

    private function separateMember(): true
    {
        $this->setTop('expectsKey', $this->topBracket() === '{');

        return true;
    }

    private function scalar(string $text, int $offset): true
    {
        $isKey = data_get(end($this->stack), 'expectsKey');
        $value = $this->decode($text);

        match ($isKey) {
            true => $this->setTop('key', $value),
            false => $this->consider($value, $text, $offset),
        };

        return true;
    }

    private function consider(?string $value, string $text, int $offset): void
    {
        $replacement = match ($this->isCommandElement()) {
            true => $this->rooted
                ->replacement((string) $value),
            false => null,
        };
        $isScript = $this->isCommandElement() && $this->rooted
            ->isScript((string) $value);
        $this->isRooted = $this->isRooted || $isScript || $replacement !== null;

        match ($replacement) {
            null => null,
            default => $this->replacements[$offset] = [strlen($text), $this->encode($replacement)],
        };
    }

    private function isCommandElement(): bool
    {
        return count($this->stack) === self::ARGUMENT_DEPTH
            && $this->keyAt(self::SERVER_DEPTH - 1) === RootedTestbench::MCP_SERVER
            && in_array($this->keyAt(self::SERVER_DEPTH), self::COMMAND_KEYS, strict: true)
            && $this->topBracket() === '[';
    }

    private function replaced(string $content): string
    {
        krsort($this->replacements);

        foreach ($this->replacements as $offset => [$length, $replacement]) {
            $content = substr_replace($content, $replacement, $offset, $length);
        }

        return $content;
    }

    private function decode(string $text): ?string
    {
        $json = match (substr($text, 0, 1)) {
            "\"" => $text,
            "'" => "\"" . str_replace(["\"", "\\'"], ["\\\"", "'"], substr($text, 1, -1)) . "\"",
            default => json_encode($text),
        };
        $value = json_decode((string) $json);

        return match (is_string($value)) {
            true => $value,
            false => null,
        };
    }

    private function encode(string $value): string
    {
        return (string) json_encode($value, self::ENCODING);
    }

    private function keyAt(int $depth): ?string
    {
        return data_get($this->stack, "{$depth}.key");
    }

    private function topBracket(): ?string
    {
        return data_get(end($this->stack), 'bracket');
    }

    private function setTop(string $field, mixed $value): void
    {
        $top = array_key_last($this->stack);

        match ($top) {
            null => null,
            default => $this->stack[$top][$field] = $value,
        };
    }
}
