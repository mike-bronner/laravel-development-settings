<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\BoostRegistrar;

function readBoostConfig(string $projectDir): array
{
    return json_decode(
        json: (string) file_get_contents($projectDir . '/boost.json'),
        associative: true,
    );
}

it('adds the package to an existing config without disturbing the other keys', function (): void {
    $project = makeTempDir();
    file_put_contents($project . '/boost.json', json_encode([
        'agents' => ['claude_code'],
        'guidelines' => true,
        'packages' => ['acme/other'],
    ]));

    $result = (new BoostRegistrar)->register($project, 'mike-bronner/laravel-development-settings');

    expect($result)->toBe(BoostRegistrar::REGISTERED)
        ->and(readBoostConfig($project))->toBe([
            'agents' => ['claude_code'],
            'guidelines' => true,
            'packages' => ['acme/other', 'mike-bronner/laravel-development-settings'],
        ]);

    removeTempDir($project);
});

it('creates the config when none exists', function (): void {
    $project = makeTempDir();

    $result = (new BoostRegistrar)->register($project, 'mike-bronner/laravel-development-settings');

    expect($result)->toBe(BoostRegistrar::REGISTERED)
        ->and(readBoostConfig($project))->toBe([
            'packages' => ['mike-bronner/laravel-development-settings'],
        ]);

    removeTempDir($project);
});

it('reports unchanged and rewrites nothing when the package is already listed', function (): void {
    $project = makeTempDir();
    file_put_contents($project . '/boost.json', json_encode([
        'packages' => ['mike-bronner/laravel-development-settings'],
    ]));
    $before = (string) file_get_contents($project . '/boost.json');

    $result = (new BoostRegistrar)->register($project, 'mike-bronner/laravel-development-settings');

    expect($result)->toBe(BoostRegistrar::UNCHANGED)
        ->and(file_get_contents($project . '/boost.json'))->toBe($before);

    removeTempDir($project);
});

it('replaces an old name of the package in place of adding beside it', function (): void {
    $project = makeTempDir();
    file_put_contents($project . '/boost.json', json_encode([
        'agents' => ['claude_code'],
        'packages' => ['acme/other', 'mikebronner/development-settings', 'acme/last'],
    ]));

    $result = (new BoostRegistrar)->register(
        projectDir: $project,
        package: 'mike-bronner/laravel-development-settings',
        replaces: ['mikebronner/development-settings'],
    );

    expect($result)->toBe(BoostRegistrar::REGISTERED)
        ->and(readBoostConfig($project))->toBe([
            'agents' => ['claude_code'],
            'packages' => ['acme/other', 'acme/last', 'mike-bronner/laravel-development-settings'],
        ]);

    removeTempDir($project);
});

it('drops an old name when the current name is already listed', function (): void {
    $project = makeTempDir();
    file_put_contents($project . '/boost.json', json_encode([
        'packages' => ['mike-bronner/laravel-development-settings', 'mikebronner/development-settings'],
    ]));

    $result = (new BoostRegistrar)->register(
        projectDir: $project,
        package: 'mike-bronner/laravel-development-settings',
        replaces: ['mikebronner/development-settings'],
    );

    expect($result)->toBe(BoostRegistrar::REGISTERED)
        ->and(readBoostConfig($project))->toBe([
            'packages' => ['mike-bronner/laravel-development-settings'],
        ]);

    removeTempDir($project);
});

it('removes only the exact old name and leaves look-alike entries to the project', function (): void {
    $project = makeTempDir();
    $contents = json_encode([
        'packages' => ['mike-bronner/laravel-development-settings', 'mikebronner/development-settings-extras', 'MikeBronner/Development-Settings'],
    ]);
    file_put_contents($project . '/boost.json', $contents);

    $result = (new BoostRegistrar)->register(
        projectDir: $project,
        package: 'mike-bronner/laravel-development-settings',
        replaces: ['mikebronner/development-settings'],
    );

    expect($result)->toBe(BoostRegistrar::UNCHANGED)
        ->and(file_get_contents($project . '/boost.json'))->toBe($contents);

    removeTempDir($project);
});

it('keeps an old name when it is not named as replaced', function (): void {
    $project = makeTempDir();
    file_put_contents($project . '/boost.json', json_encode([
        'packages' => ['mikebronner/development-settings'],
    ]));

    (new BoostRegistrar)->register($project, 'mike-bronner/laravel-development-settings');

    expect(readBoostConfig($project)['packages'])->toBe([
        'mikebronner/development-settings',
        'mike-bronner/laravel-development-settings',
    ]);

    removeTempDir($project);
});

it('refuses to touch a config that is not valid JSON', function (): void {
    $project = makeTempDir();
    file_put_contents($project . '/boost.json', "{ this is not json\n");

    $result = (new BoostRegistrar)->register($project, 'mike-bronner/laravel-development-settings');

    expect($result)->toBe(BoostRegistrar::UNREADABLE)
        ->and(file_get_contents($project . '/boost.json'))->toBe("{ this is not json\n");

    removeTempDir($project);
});

it('refuses to touch a config whose packages key is not a list', function (): void {
    $project = makeTempDir();
    $contents = json_encode(['packages' => 'mike-bronner/laravel-development-settings']);
    file_put_contents($project . '/boost.json', $contents);

    $result = (new BoostRegistrar)->register($project, 'mike-bronner/laravel-development-settings');

    expect($result)->toBe(BoostRegistrar::UNREADABLE)
        ->and(file_get_contents($project . '/boost.json'))->toBe($contents);

    removeTempDir($project);
});

it("writes Boost's own formatting contract so Boost rewriting it causes no churn", function (): void {
    $project = makeTempDir();
    file_put_contents($project . '/boost.json', json_encode(['guidelines' => true, 'agents' => []]));

    (new BoostRegistrar)->register($project, 'mike-bronner/laravel-development-settings');

    // Keys sorted, pretty-printed, slashes unescaped, trailing newline — the
    // shape Laravel\Boost\Support\Config::set() produces.
    expect(file_get_contents($project . '/boost.json'))->toBe(
        "{\n"
        . "    \"agents\": [],\n"
        . "    \"guidelines\": true,\n"
        . "    \"packages\": [\n"
        . "        \"mike-bronner/laravel-development-settings\"\n"
        . "    ]\n"
        . "}\n",
    );

    removeTempDir($project);
});

it('reports agents only when the config names at least one', function (string|false $contents, bool $expected): void {
    $project = makeTempDir();

    if ($contents !== false) {
        file_put_contents($project . '/boost.json', $contents);
    }

    expect((new BoostRegistrar)->hasAgents($project))->toBe($expected);

    removeTempDir($project);
})->with([
    'agents listed' => [json_encode(['agents' => ['claude_code']]), true],
    'no file' => [false, false],
    'packages only, as the registrar writes it' => [json_encode(['packages' => ['mike-bronner/laravel-development-settings']]), false],
    'empty agents list' => [json_encode(['agents' => []]), false],
    'agents not a list' => [json_encode(['agents' => 'claude_code']), false],
    'not valid JSON' => ['{ this is not json', false],
]);
