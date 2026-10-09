<?php

declare(strict_types=1);

function phpCommand(string $script): string
{
    $binary = escapeshellarg(PHP_BINARY);
    $code = escapeshellarg($script);

    return "{$binary} -r {$code}";
}
