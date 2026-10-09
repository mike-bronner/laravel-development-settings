<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Plugin;

use Symfony\Component\Console\Formatter\OutputFormatter;

final class ConsoleStyle
{
    public function wrap(string $style, string $text): string
    {
        return sprintf('<%1$s>%2$s</%1$s>', $style, $text);
    }

    public function escape(string $text): string
    {
        return OutputFormatter::escape($text);
    }
}
