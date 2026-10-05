<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\BoostHooks;

it('reads each hook from the config, or falls back to its default', function (
    array $config,
    array $expected,
): void {
    $hooks = new BoostHooks($config);

    expect([
        $hooks->command(),
        $hooks->interactiveCommand(),
        $hooks->discoverCommand(),
        $hooks->description(),
    ])->toBe($expected);
})->with([
    'named in the config' => [
        [
            'command' => 'captured',
            'interactive_command' => 'attached',
            'discover_command' => 'discover',
            'description' => 'Composing...',
        ],
        ['captured', 'attached', 'discover', 'Composing...'],
    ],
    'left out' => [
        [],
        [
            'php artisan boost:install --no-interaction',
            'php artisan boost:install',
            'php artisan package:discover',
            'Composing Laravel Boost...',
        ],
    ],
]);
