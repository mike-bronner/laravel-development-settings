<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Tests\Fixtures;

use MikeBronner\DevelopmentSettings\Support\Terminal;
use Override;

/**
 * A process that runs on no terminal, as CI and a piped Composer run do.
 */
final class DetachedTerminal implements Terminal
{
    #[Override]
    public function isAttached(): bool
    {
        return false;
    }
}
