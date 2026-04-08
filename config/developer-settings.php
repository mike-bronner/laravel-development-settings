<?php

declare(strict_types=1);

return [
    'composer' => [
        'install' => [
            'larastan/larastan' => '^3.5',
            'laravel/boost' => '^2.0',
            'laravel/pint' => '^1.24',
            'slevomat/coding-standard' => '^8.24',
        ],
        'remove' => [
            //
        ],
    ],

    'hooks' => [
        'patterns' => ['.ai/**'],
        'command' => 'php artisan boost:update',
        'description' => 'Updating Laravel Boost...',
    ],

    'paths' => [
        'directories' => [
            '.ai',
            '.php-codesniffer',
        ],

        'files' => [
            '.gitignore',
            'phpcs.xml',
            'phpmd.xml',
            'pint.json',
            '.github/workflows/sync-developer-settings.yml',
        ],
    ],
];
