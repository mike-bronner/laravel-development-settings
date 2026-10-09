<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Tests\Fixtures;

use MikeBronner\DevelopmentSettings\Support\Terminal;
use Override;

final class AttachedTerminal implements Terminal
{
    #[Override]
    public function isAttached(): bool
    {
        return true;
    }
}
