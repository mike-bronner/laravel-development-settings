<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\BoostHooks;
use MikeBronner\DevelopmentSettings\Support\FileDiscovery;
use MikeBronner\DevelopmentSettings\Support\PackageConfig;

it('reads each entry the config names', function (): void {
    $config = new PackageConfig([
        'capture' => ['resources/boost'],
        'paths' => ['files' => ['pint.json'], 'managed' => ['.gitignore'], 'ignore' => ['x']],
        'hooks' => ['description' => 'Composing...'],
    ]);

    expect([
        $config->paths(),
        $config->entries(PackageConfig::CAPTURE),
        $config->entries(PackageConfig::MANAGED),
        $config->entries(PackageConfig::IGNORE),
        $config->hooks()->description(),
    ])->toBe([
        ['files' => ['pint.json'], 'managed' => ['.gitignore'], 'ignore' => ['x']],
        ['resources/boost'],
        ['.gitignore'],
        ['x'],
        'Composing...',
    ]);
});

it('applies the default of an entry left out', function (string $key, array $default): void {
    expect((new PackageConfig(['paths' => []]))->entries($key))->toBe($default);
})->with([
    'capture' => [PackageConfig::CAPTURE, []],
    'managed' => [PackageConfig::MANAGED, []],
    'legacy symlinks' => [PackageConfig::LEGACY_SYMLINKS, []],
    'ignore' => [PackageConfig::IGNORE, FileDiscovery::DEFAULT_IGNORE],
]);

it('refuses a config that names no paths to sync', function (array $config): void {
    (new PackageConfig($config))->paths();
})->throws(InvalidArgumentException::class, 'names no paths')
    ->with([
        'no paths key' => [[]],
        'paths not a list' => [['paths' => 'pint.json']],
    ]);

it('reads the shipped config, --no-interaction on the captured command only', function (): void {
    $config = new PackageConfig(require REPOSITORY_ROOT . '/' . PackageConfig::FILE);

    $hooks = $config->hooks();
    $testbench = $hooks->throughTestbench();

    expect($config->entries(PackageConfig::LEGACY_SYMLINKS))->toBe(['.ai'])
        ->and([$hooks->command(), $hooks->interactiveCommand()])
        ->toBe(['php artisan boost:install --no-interaction', 'php artisan boost:install'])
        ->and([
            $testbench->command(),
            $testbench->interactiveCommand(),
            $testbench->discoverCommand(),
        ])
        ->toBe([
            BoostHooks::TESTBENCH_INSTALL_COMMAND . ' --no-interaction',
            BoostHooks::TESTBENCH_INSTALL_COMMAND,
            BoostHooks::DISCOVER_COMMAND,
        ]);
});

it('ships no file a package repository alone receives', function (): void {
    $config = require REPOSITORY_ROOT . '/' . PackageConfig::FILE;

    expect($config)->not
        ->toHaveKey('package');
});
