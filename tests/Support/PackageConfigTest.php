<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\FileDiscovery;
use MikeBronner\DevelopmentSettings\Support\PackageConfig;

it('reads each entry the config names', function (): void {
    $config = new PackageConfig([
        'capture' => ['resources/boost'],
        'package' => ['files' => ['a' => 'b'], 'managed' => ['b']],
        'paths' => ['files' => ['pint.json'], 'managed' => ['.gitignore'], 'ignore' => ['x']],
        'hooks' => ['description' => 'Composing...'],
    ]);

    expect([
        $config->paths(),
        $config->entries(PackageConfig::CAPTURE),
        $config->entries(PackageConfig::PACKAGE_MANAGED),
        $config->entries(PackageConfig::MANAGED),
        $config->entries(PackageConfig::IGNORE),
        $config->hooks()->description(),
    ])->toBe([
        ['files' => ['pint.json'], 'managed' => ['.gitignore'], 'ignore' => ['x']],
        ['resources/boost'],
        ['b'],
        ['.gitignore'],
        ['x'],
        'Composing...',
    ]);
});

it('applies the default of an entry left out', function (string $key, array $default): void {
    expect((new PackageConfig(['paths' => []]))->entries($key))->toBe($default);
})->with([
    'capture' => [PackageConfig::CAPTURE, []],
    'package' => [PackageConfig::PACKAGE, []],
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

    expect($config->entries(PackageConfig::LEGACY_SYMLINKS))->toBe(['.ai'])
        ->and([$hooks->command(), $hooks->interactiveCommand()])
        ->toBe(['php artisan boost:install --no-interaction', 'php artisan boost:install']);
});
