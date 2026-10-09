<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\SystemProcess;

const PHPCS_ELSE_SNIFF = 'CleanCode.Conditionals.DisallowElse.Found';

const PHPCS_ELSE_SOURCE = <<<PHP
    <?php

    declare(strict_types=1);

    if (true) { echo 'a'; } else { echo 'b'; }

    PHP;

function phpcsProject(array $relativePaths): string
{
    $project = makeTempDir('devset-phpcs-');

    copy(REPOSITORY_ROOT . '/phpcs.xml', "{$project}/phpcs.xml");
    seedFiles($project, array_fill_keys($relativePaths, PHPCS_ELSE_SOURCE));

    return $project;
}

function runPhpcs(string $project): array
{
    $phpcs = escapeshellarg(REPOSITORY_ROOT . '/vendor/bin/phpcs');
    $result = (new SystemProcess)
        ->capture(escapeshellarg(PHP_BINARY) . " {$phpcs} -q --report=json", $project);
    $report = json_decode($result->output(), associative: true);
    $root = strlen((string) realpath($project)) + 1;

    expect($report)->toBeArray("PHP_CodeSniffer printed no report: {$result->output()}");

    return collect(data_get($report, 'files'))
        ->mapWithKeys(fn (array $file, string $path): array => [
            substr((string) realpath($path), $root) => collect(data_get($file, 'messages'))
                ->pluck('source')
                ->unique()
                ->values()
                ->all(),
        ])
        ->sortKeys()
        ->all();
}
