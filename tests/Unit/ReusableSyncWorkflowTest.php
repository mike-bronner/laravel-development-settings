<?php

declare(strict_types=1);

/*
 * The package ships the calling workflow, so it runs the reverse sync on
 * itself. Its root .gitignore differs from the shipped one on purpose, and a
 * run here proposed the root copy over the shipped source (PR #32). The guard
 * sits on the job, directly under its key, so every step is skipped.
 */
it('skips the reverse sync when the package itself is the caller', function (): void {
    $workflow = file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/reusable-sync.yml');

    expect($workflow)->toMatch(
        "/^  sync:\n(?:    #.*\n)*    if: github\.repository != 'mike-bronner\/laravel-development-settings'\n/m",
    );
});
