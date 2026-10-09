<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use Override;

final class SystemTerminal implements Terminal
{
    #[Override]
    public function isAttached(): bool
    {
        return stream_isatty(STDIN) && stream_isatty(STDOUT);
    }
}
