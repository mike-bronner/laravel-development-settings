<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\SystemProcess;

/*
 * Pest loads tests/Helpers in the order the filesystem lists it, and that
 * order differs between macOS and Linux. A helper that needs a sibling while
 * it loads therefore passes on one and fails on the other. Loading them in
 * both sorted orders (glob() sorts its result) catches that on every machine:
 * whichever way the two files sort, one order loads the dependent file first.
 *
 * It runs in a separate PHP process because the helpers are already loaded in
 * this one.
 */

const HELPERS_LOADED = 'helpers loaded';

const HELPER_FILES = REPOSITORY_ROOT . '/tests/Helpers/*.php';

it('loads the helpers in any order', function (array $helpers): void {
    $script = sprintf(
            'require %s; foreach (%s as $helper) { require_once $helper; } echo %s;',
            var_export(REPOSITORY_ROOT . '/vendor/autoload.php', true),
            var_export($helpers, true),
            var_export(HELPERS_LOADED, true),
        );

    $result = (new SystemProcess)->capture(phpCommand($script));

    expect($result->output())->toBe(HELPERS_LOADED)
        ->and($result->exitCode())
        ->toBe(0);
})->with([
    'ascending' => [glob(HELPER_FILES)],
    'descending' => [array_reverse(glob(HELPER_FILES))],
]);
