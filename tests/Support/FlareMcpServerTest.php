<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\FlareMcpServer;

beforeEach(function (): void {
    $this->project = makeTempDir('devset-flare-server-');
    $this->isWanted = fn (): bool => (new FlareMcpServer)->isWantedIn($this->project);
});

afterEach(function (): void {
    removeTempDir($this->project);
});

it('is wanted when a Flare client is installed', function (string $client): void {
    seedFiles($this->project, ["vendor/{$client}/composer.json" => '{}']);

    expect(($this->isWanted)())->toBeTrue();
})->with(FlareMcpServer::CLIENTS);

it('is not wanted without a Flare client', function (array $files): void {
    seedFiles($this->project, $files);

    expect(($this->isWanted)())->toBeFalse();
})->with([
    'no vendor directory' => [[]],
    'another spatie package' => [['vendor/spatie/laravel-ignition/composer.json' => '{}']],
    'a client directory Composer did not install' => [
        ['vendor/spatie/laravel-flare/README.md' => ''],
    ],
]);
