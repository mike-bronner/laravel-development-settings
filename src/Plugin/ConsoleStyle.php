<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Plugin;

use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * Wraps text in a Symfony console style, the way Composer renders its output.
 */
final class ConsoleStyle
{
    /**
     * The text inside the style's opening and closing tag.
     */
    public function wrap(string $style, string $text): string
    {
        return sprintf('<%1$s>%2$s</%1$s>', $style, $text);
    }

    /**
     * Text from elsewhere, escaped so a tag inside it is printed, not styled.
     */
    public function escape(string $text): string
    {
        return OutputFormatter::escape($text);
    }
}
