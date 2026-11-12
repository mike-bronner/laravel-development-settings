<?php

declare(strict_types=1);

use Composer\Util\ErrorHandler;
use MikeBronner\DevelopmentSettings\Support\SystemProcess;

const CACHING_PHP = ' -d opcache.enable_cli=1 -d opcache.validate_timestamps=1'
    . ' -d opcache.revalidate_freq=2 -d opcache.file_update_protection=0';

const FILE_CACHING_PHP = ' -d opcache.enable_cli=1 -d opcache.validate_timestamps=0'
    . ' -d opcache.file_update_protection=0 -d opcache.jit=disable -d opcache.file_cache=%s';

const NO_FILE_CACHE = 'The file cache stored no compiled copy, so it cannot serve a stale one.';

const FILE_CACHED_RUN = <<<PHP
    \$loader = require %s;
    \$loader->setPsr4(%s, [%s]);
    %s::register();
    foreach (glob(%s) as \$helper) {
        require_once \$helper;
    }
    echo json_encode(%s);
    PHP;

const RESTRICTED_API = ' -d opcache.restrict_api=/nowhere-this-run-starts';

const NO_OPCACHE = 'PHP has no OPcache to hold a stale copy.';

const OPCACHE_API = [
    'OPcache API open to the run' => [''],
    'OPcache API restricted to another path' => [RESTRICTED_API],
];

function phpCommand(string $script): string
{
    $binary = escapeshellarg(PHP_BINARY);
    $code = escapeshellarg($script);

    return "{$binary} -r {$code}";
}

function cachingPhpCommand(string $script, string $restriction): string
{
    $binary = escapeshellarg(PHP_BINARY);
    $code = escapeshellarg($script);

    return "{$binary}" . CACHING_PHP . "{$restriction} -r {$code}";
}

function fileCacheHolds(string $packageDir, string $file): bool
{
    return glob("{$packageDir}/opcache/*" . realpath($file) . '.bin') !== [];
}

function runWithFileCache(string $packageDir, string $expression): array
{
    $cacheDir = "{$packageDir}/opcache";
    is_dir($cacheDir) || mkdir($cacheDir);
    $script = sprintf(
            FILE_CACHED_RUN,
            var_export(REPOSITORY_ROOT . '/vendor/autoload.php', true),
            var_export('MikeBronner\\DevelopmentSettings\\', true),
            var_export("{$packageDir}/src", true),
            ErrorHandler::class,
            var_export(REPOSITORY_ROOT . '/tests/Helpers/*.php', true),
            $expression,
        );
    $flags = sprintf(FILE_CACHING_PHP, escapeshellarg($cacheDir));
    $command = escapeshellarg(PHP_BINARY) . $flags . ' -r ' . escapeshellarg($script);
    $result = (new SystemProcess)->capture($command);

    return json_decode($result->output(), associative: true) ?? [];
}
