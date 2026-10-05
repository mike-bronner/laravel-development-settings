<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\SystemProcess;
use MikeBronner\DevelopmentSettings\Support\SystemTerminal;

it('says there is no terminal when its streams are pipes', function (): void {
    $autoload = var_export(REPOSITORY_ROOT . '/vendor/autoload.php', true);
    $terminal = SystemTerminal::class;

    $result = (new SystemProcess)->capture(phpCommand(
            "require {$autoload}; var_export((new {$terminal}())->isAttached());",
        ));

    expect($result->output())->toBe('false');
});
