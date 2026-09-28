<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\BoostHooks;

it('reads the commands and the description the config names', function (): void {
    $hooks = new BoostHooks([
        'command' => 'captured',
        'interactive_command' => 'attached',
        'description' => 'Composing...',
    ]);

    expect([$hooks->command(), $hooks->interactiveCommand(), $hooks->description()])
        ->toBe(['captured', 'attached', 'Composing...']);
});

it('falls back to installing Boost, captured without prompts', function (): void {
    $hooks = new BoostHooks([]);

    expect([$hooks->command(), $hooks->interactiveCommand(), $hooks->description()])->toBe([
        'php artisan boost:install --no-interaction',
        'php artisan boost:install',
        'Composing Laravel Boost...',
    ]);
});
