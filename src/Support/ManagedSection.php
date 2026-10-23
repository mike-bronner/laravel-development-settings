<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use LogicException;

final class ManagedSection
{
    public const MARKER = <<<MARKER
        # mike-bronner/laravel-development-settings: project entries go below this line.
        MARKER . ' Anything above it is lost on the next sync.';

    public const PROJECT_FIRST_MARKER = <<<MARKER
        # mike-bronner/laravel-development-settings: project entries go above this line.
        MARKER . ' Anything below it is lost on the next sync.';

    public const PROJECT_BELOW = 'below';

    public const PROJECT_ABOVE = 'above';

    private const MARKERS = [
        self::PROJECT_BELOW => self::MARKER,
        self::PROJECT_ABOVE => self::PROJECT_FIRST_MARKER,
    ];

    public function __construct(private string $project = self::PROJECT_BELOW)
    {
    }

    public function marker(): string
    {
        return self::MARKERS[$this->project];
    }

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
            $this->project === self::PROJECT_ABOVE => $project
                . self::PROJECT_FIRST_MARKER . "\n{$managed}",
            $managed === '',
            str_ends_with($managed, "\n") => $managed . self::MARKER . "\n{$project}",
            default => throw new LogicException('A managed source must end with a newline.'),
        };
    }

    private function parts(string $contents, array $matches): array
    {
        [[[$line, $offset]]] = $matches;
        $before = substr($contents, 0, $offset);
        $after = substr($contents, $this->afterMarker($contents, $offset + strlen($line)));

        return match ($this->project) {
            self::PROJECT_ABOVE => ['managed' => $after, 'project' => $before],
            default => ['managed' => $before, 'project' => $after],
        };
    }

    private function afterMarker(string $contents, int $lineEnd): int
    {
        return match (substr($contents, $lineEnd, 1)) {
            "\n" => $lineEnd + 1,
            default => $lineEnd,
        };
    }

    private function pattern(): string
    {
        return '/^' . preg_quote($this->marker(), '/') . '[ \t\r]*$/m';
    }
}
