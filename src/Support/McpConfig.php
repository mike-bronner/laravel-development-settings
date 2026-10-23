<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

interface McpConfig
{
    public function mayBeConfig(string $prefix): bool;

    public function rewrite(string $content): ?string;
}
