<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\GuidelineGuard;

function agentBlock(string $rules = 'rules'): string
{
    $closing = GuidelineGuard::CLOSING_TAG;

    return GuidelineGuard::OPENING_TAG . "\n=== {$rules} ===\n{$closing}\n";
}

function agentFile(string $prose): string
{
    $block = agentBlock('foundation rules');

    return <<<MARKDOWN
        # CLAUDE.md

        {$prose}

        {$block}
        MARKDOWN;
}

function managedAgentFile(): string
{
    return agentFile('Project notes.');
}

function armedAgentFile(): string
{
    $opening = GuidelineGuard::OPENING_TAG;

    return agentFile("Boost replaces the block between {$opening} and its end.");
}
