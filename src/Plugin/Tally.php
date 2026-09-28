<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Plugin;

/**
 * The counts one sync reports in its summary line.
 */
final class Tally
{
    public const NEW = 'new';

    public const UPDATED = 'updated';

    public const UNCHANGED = 'unchanged';

    public const SKIPPED = 'skipped';

    public const REMOVED = 'removed';

    /**
     * @var array<string, int>
     */
    private array $counts = [
        self::NEW => 0,
        self::UPDATED => 0,
        self::UNCHANGED => 0,
        self::SKIPPED => 0,
        self::REMOVED => 0,
    ];

    public function add(string $kind, int $count = 1): void
    {
        $this->counts[$kind] += $count;
    }

    public function count(string $kind): int
    {
        return $this->counts[$kind];
    }
}
