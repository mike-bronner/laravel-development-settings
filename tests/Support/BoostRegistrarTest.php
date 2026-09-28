<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\BoostRegistrar;

const REGISTERED_NAME = 'mike-bronner/laravel-development-settings';

const REPLACED_NAME = 'mikebronner/development-settings';

beforeEach(function (): void {
    $this->project = makeTempDir();
    $this->config = "{$this->project}/boost.json";
});

afterEach(function (): void {
    removeTempDir($this->project);
});

it('lists the package, drops only the replaced name, and keeps the rest', function (
    ?array $before,
    array $replaces,
    array $after,
): void {
    match ($before) {
        null => null,
        default => file_put_contents($this->config, json_encode($before)),
    };

    $result = (new BoostRegistrar())->register($this->project, REGISTERED_NAME, $replaces);

    expect($result)->toBe(BoostRegistrar::REGISTERED)
        ->and(json_decode((string) file_get_contents($this->config), associative: true))
        ->toBe($after);
})->with([
    'beside the other keys' => [
        ['agents' => ['claude_code'], 'guidelines' => true, 'packages' => ['acme/other']],
        [],
        [
            'agents' => ['claude_code'],
            'guidelines' => true,
            'packages' => ['acme/other', REGISTERED_NAME],
        ],
    ],
    'in a config it creates' => [null, [], ['packages' => [REGISTERED_NAME]]],
    'in place of its old name' => [
        ['agents' => ['claude_code'], 'packages' => ['acme/other', REPLACED_NAME, 'acme/last']],
        [REPLACED_NAME],
        ['agents' => ['claude_code'], 'packages' => ['acme/other', 'acme/last', REGISTERED_NAME]],
    ],
    'dropping its old name beside its current one' => [
        ['packages' => [REGISTERED_NAME, REPLACED_NAME]],
        [REPLACED_NAME],
        ['packages' => [REGISTERED_NAME]],
    ],
    'keeping an old name not named as replaced' => [
        ['packages' => [REPLACED_NAME]],
        [],
        ['packages' => [REPLACED_NAME, REGISTERED_NAME]],
    ],
]);

it('leaves the file byte for byte as it was, unless it has to change it', function (
    string $contents,
    string $result,
): void {
    file_put_contents($this->config, $contents);

    expect((new BoostRegistrar())->register($this->project, REGISTERED_NAME, [REPLACED_NAME]))
        ->toBe($result)
        ->and(file_get_contents($this->config))
        ->toBe($contents);
})->with([
    'already listed' => [json_encode(['packages' => [REGISTERED_NAME]]), BoostRegistrar::UNCHANGED],
    'look-alike entries only' => [
        json_encode(['packages' => [REGISTERED_NAME, REPLACED_NAME . '-x', 'MikeBronner/Dev']]),
        BoostRegistrar::UNCHANGED,
    ],
    'not valid JSON' => ["{ this is not json\n", BoostRegistrar::UNREADABLE],
    'packages not a list' => [
        json_encode(['packages' => REGISTERED_NAME]),
        BoostRegistrar::UNREADABLE,
    ],
]);

it("writes Boost's formatting contract, so Boost rewriting it causes no churn", function (): void {
    file_put_contents($this->config, json_encode(['guidelines' => true, 'agents' => []]));

    (new BoostRegistrar())->register($this->project, REGISTERED_NAME);

    expect(file_get_contents($this->config))->toBe(<<<JSON
        {
            "agents": [],
            "guidelines": true,
            "packages": [
                "mike-bronner/laravel-development-settings"
            ]
        }

        JSON);
});

it('reports agents when the config names at least one', function (): void {
    file_put_contents($this->config, json_encode(['agents' => ['claude_code']]));

    expect((new BoostRegistrar())->hasAgents($this->project))->toBeTrue();
});

it('reports no agents when the config names none', function (?string $contents): void {
    match ($contents) {
        null => null,
        default => file_put_contents($this->config, $contents),
    };

    expect((new BoostRegistrar())->hasAgents($this->project))->toBeFalse();
})->with([
    'no file' => [null],
    'packages only' => [json_encode(['packages' => [REGISTERED_NAME]])],
    'empty agents list' => [json_encode(['agents' => []])],
    'agents not a list' => [json_encode(['agents' => 'claude_code'])],
    'not valid JSON' => ['{ this is not json'],
]);
