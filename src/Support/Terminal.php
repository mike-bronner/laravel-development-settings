<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

interface Terminal
{
    public function isAttached(): bool;
}
