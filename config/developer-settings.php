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
            '.php-codesniffer',
        ],

        'files' => [
            '.gitignore',
            'phpcs.xml',
            'phpmd.xml',
            'pint.json',
            '.github/workflows/sync-developer-settings.yml',
        ],

        // Symlinked (not copied) into the consuming project: link path => source
        // path within this package. Kept out of the project's git/distribution
        // while staying in sync with vendor and feeding Laravel Boost directly.
        'symlinks' => [
            '.ai' => '.ai',
        ],

        // File/directory names excluded from discovery anywhere in the tree.
        'ignore' => [
            '.DS_Store',
            '.git',
            'Thumbs.db',
        ],
    ],
];
