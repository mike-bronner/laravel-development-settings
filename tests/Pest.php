<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
|
| The suite runs on plain PHPUnit test cases: this package is a Composer
| plugin, not a Laravel application, so there is no framework TestCase to
| bind. The helper files hold declarations only, so every test file can use
| them without declaring functions of its own.
|
*/

require_once __DIR__ . '/Helpers/filesystem.php';
require_once __DIR__ . '/Helpers/managed.php';
require_once __DIR__ . '/Helpers/agents.php';
require_once __DIR__ . '/Helpers/process.php';
require_once __DIR__ . '/Helpers/consumer.php';
require_once __DIR__ . '/Helpers/shipped.php';
require_once __DIR__ . '/Helpers/phpcs.php';
require_once __DIR__ . '/Helpers/workflow.php';
