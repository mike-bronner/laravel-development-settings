<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

/**
 * One rule of an ignore file, read the way git reads it: a leading `!`
 * negates it, a trailing `/` limits it to directories, a `/` anywhere else
 * anchors it at the root, and `*`, `?`, `**`, bracket expressions and
 * backslash escapes match as git matches them.
 *
 * A rule is project text, and reading it must never stop a publish: under
 * Composer, a PCRE warning becomes an exception. So every character that is
 * not a wildcard is quoted, and a bracket expression with a range out of
 * order, or with no character at all, makes the rule match nothing.
 */
final class IgnorePattern
{
    private const DELIMITER = '#';

    private const TOKENS = '#\\\\.|/\*\*/|\*\*/|/\*\*|\*|\?|\[[!^]?\]?(?:\\\\.|[^\]\\\\])*\]|.#s';

    private const TRAILING_SPACES = '/(?<!\\\\) +$/';

    private const ESCAPED = '#^\\\\.$#s';

    private const BRACKET = '#^\[(?<negation>[!^]?)(?<items>.*)\]$#s';

    private const BRACKET_ITEMS = '#(?<start>\\\\.|.)(?:-(?<end>\\\\.|[^\\\\]))?#s';

    private const GLOB = [
        '/**/' => '/(?:.*/)?',
        '**/' => '(?:.*/)?',
        '/**' => '/.*',
        '*' => '[^/]*',
        '?' => '[^/]',
    ];

    public function __construct(private string $rule)
    {
    }

    /**
     * The rule as git reads it: without a carriage return, and without the
     * trailing spaces a backslash does not escape.
     */
    public function rule(): string
    {
        return (string) preg_replace(self::TRAILING_SPACES, '', rtrim($this->rule, "\r"));
    }

    public function isNegation(): bool
    {
        return str_starts_with($this->rule(), '!');
    }

    /**
     * Whether the rule matches the path. A directory ends with a slash.
     */
    public function matches(string $path): bool
    {
        $body = $this->body();
        $pattern = rtrim($body, '/');
        $name = rtrim($path, '/');
        $subject = match (str_contains($pattern, '/')) {
            true => $name,
            false => basename($name),
        };
        $isAllowed = str_ends_with($path, '/') || ! str_ends_with($body, '/');
        $regex = $this->compile(ltrim($pattern, '/'));

        return $regex !== null && $isAllowed && preg_match($regex, $subject) === 1;
    }

    /**
     * The rule without the `!` that negates it.
     */
    private function body(): string
    {
        return match ($this->isNegation()) {
            true => substr($this->rule(), 1),
            false => $this->rule(),
        };
    }

    private function compile(string $glob): ?string
    {
        preg_match_all(self::TOKENS, $glob, $matches);
        $parts = collect(data_get($matches, 0, []))
            ->map(fn (string $token): ?string => $this->token($token));
        $regex = self::DELIMITER . "^{$parts->implode('')}$" . self::DELIMITER;

        return match (true) {
            $glob === '',
            $parts->contains(null) => null,
            default => $regex,
        };
    }

    private function token(string $token): ?string
    {
        return match (true) {
            array_key_exists($token, self::GLOB) => (string) collect(self::GLOB)->get($token),
            preg_match(self::ESCAPED, $token) === 1 => $this->quote($this->unescape($token)),
            preg_match(self::BRACKET, $token) === 1 => $this->bracket($token),
            default => $this->quote($token),
        };
    }

    /**
     * A bracket expression as a character class, or null when it holds no
     * character or a range out of order.
     */
    private function bracket(string $token): ?string
    {
        preg_match(self::BRACKET, $token, $parts);
        $negation = match (data_get($parts, 'negation')) {
            '' => '',
            default => '^',
        };
        $content = (string) data_get($parts, 'items');
        preg_match_all(self::BRACKET_ITEMS, $content, $items, PREG_SET_ORDER);
        $ranges = collect($items)->map(fn (array $item): ?string => $this->range(
                $this->unescape((string) data_get($item, 'start')),
                $this->unescape((string) data_get($item, 'end', '')),
            ));

        return match (true) {
            $ranges->isEmpty(),
            $ranges->contains(null) => null,
            default => "[{$negation}{$ranges->implode('')}]",
        };
    }

    private function range(string $start, string $end): ?string
    {
        return match (true) {
            $end === '' => $this->quote($start),
            strcmp($start, $end) > 0 => null,
            default => "{$this->quote($start)}-{$this->quote($end)}",
        };
    }

    private function unescape(string $item): string
    {
        return match (preg_match(self::ESCAPED, $item)) {
            1 => substr($item, 1),
            default => $item,
        };
    }

    private function quote(string $text): string
    {
        return preg_quote($text, self::DELIMITER);
    }
}
