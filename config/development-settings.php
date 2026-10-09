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
        ],

        'managed' => [
            '.gitignore',
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
