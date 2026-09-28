<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

/**
 * Answers whether this process runs on a terminal. Extracted so a test can
 * say what a real Composer run would detect: the suite's own streams are not
 * the ones a developer's Composer run has.
 */
interface Terminal
{
    public function isAttached(): bool;
}
