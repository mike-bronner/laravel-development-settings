<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Providers;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use MikeBronner\DevelopmentSettings\Support\FileDiscovery;

final class ServiceProvider extends BaseServiceProvider
{
    public function boot(): void
    {
        $publishPaths = [];

        $tracked = FileDiscovery::trackedPaths(config('development-settings.paths.directories'))
            + FileDiscovery::trackedPaths(config('development-settings.paths.files'));

        foreach ($tracked as $target => $source) {
            $publishPaths[__DIR__ . '/../../' . $source] = base_path($target);
        }

        $this->publishes(paths: $publishPaths, groups: 'development-settings');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            path: __DIR__ . '/../../config/development-settings.php',
            key: 'development-settings',
        );
    }
}
