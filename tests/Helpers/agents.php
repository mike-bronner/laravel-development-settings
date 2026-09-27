<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\GuidelineGuard;

/**
 * A composed Boost block: the opening tag, generated content, the closing tag.
 */
function agentBlock(string $rules = 'rules'): string
{
    $closing = GuidelineGuard::CLOSING_TAG;

    return GuidelineGuard::OPENING_TAG . "\n=== {$rules} ===\n{$closing}\n";
}

/**
 * An agent file: a heading, the project's own prose, and a composed block.
 */
function agentFile(string $prose): string
{
    $block = agentBlock('foundation rules');

    return <<<MARKDOWN
        # CLAUDE.md

        {$prose}

        {$block}
        MARKDOWN;
}

/**
 * The shape Boost leaves behind: one opening tag, generated content, one
 * closing tag, and the project's own writing around it.
 */
function managedAgentFile(): string
{
    return agentFile('Project notes.');
}

/**
 * The shape that destroys work: a hand-written section names the opening tag
 * in prose, and the real block follows it.
 */
function armedAgentFile(): string
{
    $opening = GuidelineGuard::OPENING_TAG;

    return agentFile("Boost replaces the block between {$opening} and its end.");
}
