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
        // Packages named here are dropped from a project's require-dev. The
        // list is empty on purpose: slevomat/coding-standard is not removed,
        // because mike-bronner/clean-code requires it, so `composer remove`
        // could never succeed and every project would report a failure.
        'remove' => [],
    ],

    // Boost composition command. It runs wherever the project has an artisan:
    // a full Laravel app's own, or, in a package repository, the shim listed
    // under `package` below, which boots Orchestra Testbench rooted at the
    // repository. Either way Boost reads the project's composer files and
    // writes boost.json, the skills, the agent files and its MCP entries into
    // the project.
    //
    // On the captured `command` the plugin appends --guidelines --skills --mcp,
    // so every project gets all three Boost features, whatever its boost.json
    // says.
    //
    // `boost:install`, not `boost:update`: `boost.json` is gitignored, so a
    // fresh clone has none, and `boost:update` composes nothing from a config
    // that enables no guidelines or skills. `install` is the command that
    // writes the config it needs. Agents come from `boost.json` when it names
    // any, and otherwise from what Boost detects on the machine.
    //
    // `command` runs captured, without prompts: in CI, under --no-interaction,
    // and whenever Composer has no terminal. `interactive_command` runs, as
    // written and with no feature flags, when Composer is interactive on a
    // terminal. It gets the terminal, as a Composer script does, so Boost's
    // own prompts choose the features, packages and agents, and Boost saves
    // the agents to boost.json. With a feature flag it would ask for agents
    // and not save them. They are two keys, not one with the flag added in
    // code, so a plugin still running from before an update keeps
    // `--no-interaction`.
    'hooks' => [
        'command' => 'php artisan boost:install --no-interaction',
        'interactive_command' => 'php artisan boost:install',
        'description' => 'Composing Laravel Boost...',
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

    // Files synced only into a package repository: one with no artisan of its
    // own and with vendor/bin/testbench installed. They follow the rules of
    // `paths` below, with their own checksums in package-manifest.json. They
    // stay out of `paths` and manifest.json because an app holds its own
    // artisan, and copy-sync would report it as locally modified while orphan
    // cleanup offered to delete it.
    //
    // The artisan shim roots Testbench at the repository, so Boost, its MCP
    // server and every other Artisan command work there as in an app. The
    // repository commits it, and the managed .gitattributes keeps it out of
    // the package's dist archive. Its marker line tells it from an app's
    // artisan, which is never touched.
    'package' => [
        'files' => [
            'resources/project/artisan' => 'artisan',
            'resources/project/gitattributes' => '.gitattributes',
        ],
        'managed' => [
            '.gitattributes',
        ],
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
