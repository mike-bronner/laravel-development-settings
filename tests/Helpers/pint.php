<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\SystemProcess;

const PINT_TEST_FILE = 'tests/ValueTest.php';

const PINT_TEST_CLASS = <<<PHP
    <?php

    final class ValueTest extends PHPUnit\Framework\TestCase
    {
        #[PHPUnit\Framework\Attributes\Test]
        public function %s(): void {}
    }

    PHP;

const PINT_SOURCE_FILE = 'src/Layout.php';

function pintProject(string $methodName): string
{
    return pintFiles([PINT_TEST_FILE => sprintf(PINT_TEST_CLASS, $methodName)]);
}

function pintFiles(array $files): string
{
    $project = makeTempDir('devset-pint-');

    copy(REPOSITORY_ROOT . '/pint.json', "{$project}/pint.json");
    copy(REPOSITORY_ROOT . '/phpcs.xml', "{$project}/phpcs.xml");
    seedFiles($project, $files);
    $pint = escapeshellarg(REPOSITORY_ROOT . '/vendor/bin/pint');
    $paths = collect($files)
        ->keys()
        ->map(escapeshellarg(...))
        ->implode(' ');
    $result = (new SystemProcess())
        ->capture(escapeshellarg(PHP_BINARY) . " {$pint} --config pint.json {$paths}", $project);

    expect($result->exitCode())->toBe(0, "Pint failed: {$result->output()}");

    return $project;
}
