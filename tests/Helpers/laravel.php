<?php

declare(strict_types=1);

use Illuminate\Container\Container;

function base_path(string $path = ''): string
{
    return Container::getInstance()
        ->basePath($path);
}

function config(string $key, mixed $default = null): mixed
{
    return Container::getInstance()
        ->make('config')
        ->get($key, $default);
}
