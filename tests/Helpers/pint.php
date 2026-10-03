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

function pintProject(string $methodName): string
{
    $project = makeTempDir('devset-pint-');

    copy(REPOSITORY_ROOT . '/pint.json', "{$project}/pint.json");
    copy(REPOSITORY_ROOT . '/phpcs.xml', "{$project}/phpcs.xml");
    seedFiles($project, [PINT_TEST_FILE => sprintf(PINT_TEST_CLASS, $methodName)]);
    $pint = escapeshellarg(REPOSITORY_ROOT . '/vendor/bin/pint');
    $result = (new SystemProcess())
        ->capture(escapeshellarg(PHP_BINARY) . " {$pint} --config pint.json tests", $project);

    expect($result->exitCode())->toBe(0, "Pint failed: {$result->output()}");

    return $project;
}
