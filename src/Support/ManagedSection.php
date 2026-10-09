<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use LogicException;

final class ManagedSection
{
    public const MARKER = <<<MARKER
        # mike-bronner/laravel-development-settings: project entries go below this line.
        MARKER . ' Anything above it is lost on the next sync.';

    public function markers(string $contents): int
    {
        return (int) preg_match_all($this->pattern(), $contents);
    }

    public function split(string $contents): ?array
    {
        $markers = preg_match_all($this->pattern(), $contents, $matches, PREG_OFFSET_CAPTURE);

        return match ($markers) {
            1 => $this->parts($contents, $matches),
            default => null,
        };
    }

    public function managedPart(string $contents): ?string
    {
        ['managed' => $managed] = $this->split($contents) ?? ['managed' => null];

        return $managed;
    }

    public function projectPart(string $contents): ?string
    {
        ['project' => $project] = $this->split($contents) ?? ['project' => null];

        return $project;
    }

    public function compose(string $managed, string $project): string
    {
        return match (true) {
            $managed === '',
            str_ends_with($managed, "\n") => $managed . self::MARKER . "\n{$project}",
            default => throw new LogicException('A managed source must end with a newline.'),
        };
    }

    private function parts(string $contents, array $matches): array
    {
        [[[$line, $offset]]] = $matches;
        $lineEnd = $offset + strlen($line);

        return [
            'managed' => substr($contents, 0, $offset),
            'project' => substr($contents, $this->projectStart($contents, $lineEnd)),
        ];
    }

    private function projectStart(string $contents, int $lineEnd): int
    {
        return match (substr($contents, $lineEnd, 1)) {
            "\n" => $lineEnd + 1,
            default => $lineEnd,
        };
    }

    private function pattern(): string
    {
        return '/^' . preg_quote(self::MARKER, '/') . '[ \t\r]*$/m';
    }
}
