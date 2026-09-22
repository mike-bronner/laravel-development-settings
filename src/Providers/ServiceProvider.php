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

        $tracked = FileDiscovery::trackedPaths(config('developer-settings.paths.directories'))
            + FileDiscovery::trackedPaths(config('developer-settings.paths.files'));

        foreach ($tracked as $target => $source) {
            $publishPaths[__DIR__ . '/../../' . $source] = base_path($target);
        }

        $this->publishes(paths: $publishPaths, groups: 'developer-settings');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            path: __DIR__ . '/../../config/developer-settings.php',
            key: 'developer-settings',
        );
    }
}
