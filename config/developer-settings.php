<?php

declare(strict_types=1);

return [
    'composer' => [
        'install' => [
            'larastan/larastan' => '^3.5',
            // 2.9 is the floor: earlier releases keyed third-party guidelines
            // by package name inside the per-file loop, so only the last of the
            // shipped guideline files survived composition.
            'laravel/boost' => '^2.9',
            'laravel/pint' => '^1.24',
        ],
        'remove' => [
            //
        ],
    ],

    // Boost composition command, run in full Laravel apps (which have artisan).
    // Packages have no artisan and no console entry point of their own, so they
    // compose nothing — their agent files come from the application consuming
    // them, or are read straight out of resources/boost.
    'hooks' => [
        'command' => 'php artisan boost:update',
        'description' => 'Updating Laravel Boost...',
    ],

    // Tracked paths are copied into the consuming project. A plain entry names
    // one path and means the package and the project spell it the same way. A
    // keyed entry reads source => target: the package ships the file under the
    // key and the project receives it under the value. The manifest, the
    // classifier and the orphan cleanup all key on the target, so moving a
    // source changes nothing downstream.
    'paths' => [
        'directories' => [
            //
        ],

        'files' => [
            // Shipped separately from this repository's own `.gitignore` so the
            // two can differ. They served one file until they collided: the
            // shipped rules ignore the composed agent files, and this
            // repository's `CLAUDE.md` is hand-written. The source deliberately
            // sits outside `resources/boost` (Boost scans that for package
            // guidelines and skills) and deliberately carries no leading dot (a
            // dotted copy would act as a real ignore file for its own
            // directory).
            'resources/project/gitignore' => '.gitignore',
            'phpmd.xml',
            'pint.json',
            '.github/workflows/sync-developer-settings.yml',
        ],

        // Project-root symlinks earlier versions created to point at shared
        // sources inside vendor. Those sources now ship at resources/boost and
        // Laravel Boost reads them from vendor itself, so the links are removed
        // on upgrade: left in place, `.ai` stays a window into vendor that the
        // project cannot own and that dangles once the target is gone.
        'legacy_symlinks' => [
            '.ai',
        ],

        // File/directory names excluded from discovery anywhere in the tree.
        'ignore' => [
            '.DS_Store',
            '.git',
            'Thumbs.db',
        ],
    ],
];
