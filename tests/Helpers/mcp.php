<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\RootedTestbench;

const BOOST_MCP_ARGS = ['artisan', 'boost:mcp'];

const ROOTED_MCP_ARGS = [RootedTestbench::SCRIPT, 'boost:mcp'];

const BOOST_MCP_JSON = <<<JSON
    {
        "mcpServers": {
            "laravel-boost": {
                "command": "php",
                "args": [
                    "artisan",
                    "boost:mcp"
                ]
            }
        }
    }
    JSON;

const BOOST_MCP_TOML = <<<TOML
    [mcp_servers.laravel-boost]
    command = "php"
    args = ["artisan", "boost:mcp"]

    TOML;

function rootedMcpJson(): string
{
    return str_replace("\"artisan\"", "\"" . RootedTestbench::SCRIPT . "\"", BOOST_MCP_JSON);
}

function rootedMcpToml(): string
{
    return str_replace("\"artisan\"", "\"" . RootedTestbench::SCRIPT . "\"", BOOST_MCP_TOML);
}

function boostMcpServer(
    array $server = ['command' => 'php', 'args' => BOOST_MCP_ARGS],
    string $configKey = 'mcpServers',
): array {
    return [$configKey => [RootedTestbench::MCP_SERVER => $server]];
}

function mcpJson(array $config): string
{
    return (string) json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

function logOfSize(int $bytes): string
{
    $line = "[2026-10-15 10:00:00] local.INFO: boost a b c d e f g h\n";

    return str_repeat($line, intdiv($bytes, strlen($line)) + 1);
}

const NOTHING_REWRITTEN = ['configs' => [], 'unread' => []];
