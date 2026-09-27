<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

use Override;

final class SystemTerminal implements Terminal
{
    /**
     * Whether this process reads from and writes to a terminal, the same test
     * Composer makes before it gives a script the TTY.
     */
    #[Override]
    public function isAttached(): bool
    {
        return stream_isatty(STDIN) && stream_isatty(STDOUT);
    }
}
