<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Tests\Fixtures;

final class UninjectedCaller
{
    public function __construct(public string $name = '')
    {
    }
}
