<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\BoostHooks;

const NAMED_HOOKS = [
    'command' => 'captured',
    'interactive_command' => 'attached',
    'testbench_command' => 'testbench captured',
    'testbench_interactive_command' => 'testbench attached',
    'testbench_discover_command' => 'discover',
    'description' => 'Composing...',
];

it('reads each hook from the config, or falls back to its default', function (
    string $project,
    array $config,
    array $expected,
): void {
    $hooks = match ($project) {
        'a package' => (new BoostHooks($config))->throughTestbench(),
        default => new BoostHooks($config),
    };

    expect([
        $hooks->command(),
        $hooks->interactiveCommand(),
        $hooks->installCommand(),
        $hooks->discoverCommand(),
        $hooks->description(),
    ])->toBe($expected);
})->with([
    'an app, named in the config' => [
        'an app',
        NAMED_HOOKS,
        ['captured', 'attached', 'php artisan boost:install', 'discover', 'Composing...'],
    ],
    'an app, left out' => [
        'an app',
        [],
        [
            'php artisan boost:install --no-interaction',
            'php artisan boost:install',
            'php artisan boost:install',
            BoostHooks::DISCOVER_COMMAND,
            'Composing Laravel Boost...',
        ],
    ],
    'a package, named in the config' => [
        'a package',
        NAMED_HOOKS,
        [
            'testbench captured',
            'testbench attached',
            BoostHooks::TESTBENCH_INSTALL_COMMAND,
            'discover',
            'Composing...',
        ],
    ],
    'a package, left out' => [
        'a package',
        [],
        [
            BoostHooks::TESTBENCH_INSTALL_COMMAND . ' --no-interaction',
            BoostHooks::TESTBENCH_INSTALL_COMMAND,
            BoostHooks::TESTBENCH_INSTALL_COMMAND,
            BoostHooks::DISCOVER_COMMAND,
            'Composing Laravel Boost...',
        ],
    ],
]);

it('runs the rooted Testbench this package installs in vendor', function (): void {
    $script = 'php vendor/mike-bronner/laravel-development-settings/bin/rooted-testbench.php';

    expect(BoostHooks::TESTBENCH_INSTALL_COMMAND)->toBe("{$script} boost:install")
        ->and(BoostHooks::DISCOVER_COMMAND)
        ->toBe("{$script} package:discover")
        ->and(is_file(REPOSITORY_ROOT . '/bin/rooted-testbench.php'))
        ->toBeTrue();
});
