<?php

declare(strict_types=1);

/**
 * A shell command that runs the PHP code with this PHP binary.
 */
function phpCommand(string $script): string
{
    $binary = escapeshellarg(PHP_BINARY);
    $code = escapeshellarg($script);

    return "{$binary} -r {$code}";
}
