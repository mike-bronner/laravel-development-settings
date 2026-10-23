<?php

declare(strict_types=1);

namespace Laravel\Boost\Mcp;

use Illuminate\Container\Container;

function config(string $key): mixed
{
    $container = Container::getInstance();

    return match ($container->bound('config')) {
        true => $container->make('config')
            ->get($key),
        false => null,
    };
}

function base_path(string $path = ''): string
{
    return Container::getInstance()
        ->basePath($path);
}
