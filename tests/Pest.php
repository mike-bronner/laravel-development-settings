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
| Pest loads every file under tests/Helpers itself, before this file, in the
| order the filesystem lists them. That order differs between macOS and
| Linux. So a helper may use a sibling's constants and functions only inside
| a function body, which runs after every helper has loaded, and never in a
| constant expression or other code that runs while the file loads.
| HelperLoadOrderTest loads them in both sorted orders to hold that line.
|
*/
