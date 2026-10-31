<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

final class FlareMcpServer
{
    public const KEY = 'flare';

    public const URL = 'https://flareapp.io/mcp';

    public const CLIENTS = ['spatie/laravel-flare', 'spatie/flare-client-php'];

    public function isWantedIn(string $projectDir): bool
    {
        return collect(self::CLIENTS)
            ->contains(fn (string $client): bool => is_file(
                    "{$projectDir}/vendor/{$client}/composer.json",
                ));
    }
}
