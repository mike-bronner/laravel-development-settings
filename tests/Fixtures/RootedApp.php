<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Tests\Fixtures;

use Illuminate\Container\Container;

final class RootedApp extends Container
{
    public function __construct(private string $basePath)
    {
    }

    public function basePath(string $path = ''): string
    {
        return rtrim("{$this->basePath}/{$path}", '/');
    }
}
