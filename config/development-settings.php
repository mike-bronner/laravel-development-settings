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
        // PHP_CodeSniffer belongs to mike-bronner/phpcs-rules, not this
        // package. A project that also requires phpcs-rules keeps slevomat as
        // its transitive dependency.
        'remove' => [
            'slevomat/coding-standard',
        ],
    ],

    // Boost composition commands. `command` runs in full Laravel apps, through
    // their own artisan. `package_command` runs in a repository with no
    // artisan, through Orchestra Testbench, and only when vendor/bin/testbench
    // is installed.
    //
    // The package command is rooted at the repository for this one run, so
    // Boost reads the repository's composer files and writes boost.json and the
    // skills there instead of into vendor's Testbench skeleton. The plugin sets
    // APP_BASE_PATH (the repository) and APP_ENV=local on the command only:
    // Testbench reads APP_BASE_PATH from $_ENV alone, hence the
    // variables_order flag, and a rooted Testbench boots as production, where
    // Boost registers no commands. No testbench.yaml is shipped for this: it
    // would root every Testbench run, and a rooted boost:mcp cannot run a
    // single tool, because Boost runs them through the base path's artisan.
    // The plugin appends --mcp to the package command unless boost.json sets
    // "mcp": false, so a package gets its MCP entries without a hand-run
    // install; it then points them at an unrooted vendor/bin/testbench.
    //
    // `boost:install`, not `boost:update`: `boost.json` is gitignored, so a
    // fresh clone has none, and `boost:update` composes nothing from a config
    // that enables no guidelines or skills. `install` is the command that
    // writes the config it needs. The flags make it non-interactive and pin the
    // two features this package ships; agents come from `boost.json` when it
    // names any, and otherwise from what Boost detects on the machine.
    'hooks' => [
        'command' => 'php artisan boost:install --guidelines --skills --no-interaction',
        'package_command' => 'php -d variables_order=EGPCS vendor/bin/testbench boost:install --guidelines --skills --no-interaction',
        'description' => 'Composing Laravel Boost guidelines and skills...',
    ],

    // Package sources a consuming project may edit in place, inside vendor.
    // Before a Composer update overwrites them, the plugin compares each file
    // with every version the package ever shipped and offers to contribute the
    // edits upstream. The known versions live in capture-manifest.json, keyed
    // on these package paths, never in manifest.json: copy-sync and orphan
    // cleanup read that file on project paths, and would take a consuming
    // package's own resources/boost files for this package's orphans.
    'capture' => [
        'resources/boost',
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

        // Tracked targets the project shares with this package, split at one
        // marker line (Support\ManagedSection). The sync owns everything above
        // the marker and never touches anything below it, so a project keeps
        // its own rules through every update, and the reverse sync proposes
        // only edits above the marker. Each entry is a target path, and must
        // also be listed under `files`. A file without the marker is converted
        // automatically only when it is a version this package shipped.
        'managed' => [
            '.gitignore',
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
