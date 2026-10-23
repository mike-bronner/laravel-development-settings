<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\FileSync;
use MikeBronner\DevelopmentSettings\Support\ManagedSection;
use MikeBronner\DevelopmentSettings\Support\Manifest;

const GITIGNORE_V1 = "/vendor\n";

const GITIGNORE_V2 = "/vendor\n/node_modules\n";

const WORKFLOW_V1 = "name: CI\n";

const WORKFLOW_V2 = <<<YAML
    name: CI
    on: push

    YAML;

function marked(string $above, string $after = "\n"): string
{
    return $above . ManagedSection::MARKER . $after;
}

function throwingOnWarnings(Closure $callback): mixed
{
    set_error_handler(
            fn (mixed ...$error): never => throw new ErrorException((string) data_get($error, 1)),
        );

    try {
        return $callback();
    } finally {
        restore_error_handler();
    }
}

function markedFirst(string $project, string $after = "\n"): string
{
    return $project . ManagedSection::PROJECT_FIRST_MARKER . $after;
}

function projectFirstSync(): FileSync
{
    return new FileSync(
            new Manifest(['ci.yml' => [md5(WORKFLOW_V1), md5(WORKFLOW_V2)]]),
            projectFirst: ['ci.yml'],
        );
}
