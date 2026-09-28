<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Tests\Fixtures;

use MikeBronner\DevelopmentSettings\Support\Terminal;
use Override;

/**
 * A process that runs on a terminal, as an interactive Composer run on one does.
 */
final class AttachedTerminal implements Terminal
{
    #[Override]
    public function isAttached(): bool
    {
        return true;
    }
}
