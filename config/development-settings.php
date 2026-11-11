<?php

declare(strict_types=1);

return [
    'hooks' => [
        'command' => 'php artisan boost:install --no-interaction',
        'interactive_command' => 'php artisan boost:install',
        'testbench_command' => 'php vendor/mike-bronner/laravel-development-settings'
            . '/bin/rooted-testbench.php boost:install --no-interaction',
        'testbench_interactive_command' => 'php vendor/mike-bronner/laravel-development-settings'
            . '/bin/rooted-testbench.php boost:install',
        'testbench_discover_command' => 'php vendor/mike-bronner/laravel-development-settings'
            . '/bin/rooted-testbench.php package:discover',
        'testbench_mcp_check_command' => 'php vendor/mike-bronner/laravel-development-settings'
            . '/bin/rooted-testbench.php dev-settings:mcp-executor',
        'flare_mcp_command' => 'php artisan dev-settings:flare-mcp',
        'testbench_flare_mcp_command' => 'php vendor/mike-bronner/laravel-development-settings'
            . '/bin/rooted-testbench.php dev-settings:flare-mcp',
        'description' => 'Composing Laravel Boost...',
    ],

    'capture' => [
        'resources/boost',
    ],

    'paths' => [
        'directories' => [
        ],

        'files' => [
            'resources/project/gitignore' => '.gitignore',
            'phpcs.xml',
            'pint.json',
            '.github/workflows/sync-developer-settings.yml',
            'resources/project/tia-baseline.yml' => '.github/workflows/tia-baseline.yml',
            '.github/ISSUE_TEMPLATE/product-goal.md',
            '.github/ISSUE_TEMPLATE/epic.md',
            '.github/ISSUE_TEMPLATE/bug.md',
            '.github/ISSUE_TEMPLATE/enhancement.md',
            '.github/ISSUE_TEMPLATE/maintenance.md',
            '.github/ISSUE_TEMPLATE/research.md',
            '.github/pull_request_template.md',
        ],

        'managed' => [
            '.gitignore',
        ],

        'managed_project_first' => [
            '.github/workflows/tia-baseline.yml',
        ],

        'legacy_symlinks' => [
            '.ai',
        ],

        'ignore' => [
            '.DS_Store',
            '.git',
            'Thumbs.db',
        ],
    ],
];
