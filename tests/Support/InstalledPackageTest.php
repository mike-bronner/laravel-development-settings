<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\InstalledPackage;
use MikeBronner\DevelopmentSettings\Support\PackageConfig;

beforeEach(function (): void {
    $this->project = makeTempDir();
});

afterEach(function (): void {
    removeTempDir($this->project);
});

it('finds the package in the project\'s vendor directory', function (): void {
    seedFiles($this->project, ['vendor/' . InstalledPackage::NAME . '/composer.json' => '{}']);

    expect((new InstalledPackage($this->project))->directory())
        ->toBe(realpath("{$this->project}/vendor/" . InstalledPackage::NAME));
});

it('finds no package in a project that does not hold it', function (): void {
    expect((new InstalledPackage($this->project))->directory())->toBeNull();
});

it('reads the config the installed package ships', function (): void {
    $config = "<?php return ['paths' => ['files' => []]];";
    seedFiles($this->project, [PackageConfig::FILE => $config]);

    $config = (new InstalledPackage($this->project))->config($this->project);

    expect($config->paths())->toBe(['files' => []]);
});

it('knows its own repository by the name in composer.json', function (): void {
    $composerJson = json_encode(['name' => InstalledPackage::NAME]);
    file_put_contents("{$this->project}/composer.json", $composerJson);

    expect((new InstalledPackage($this->project))->isOwnRepository())->toBeTrue();
});

it('takes no other project for its own repository', function (?string $composerJson): void {
    seedFiles($this->project, collect(['composer.json' => $composerJson])->whereNotNull()->all());

    expect((new InstalledPackage($this->project))->isOwnRepository())->toBeFalse();
})->with([
    'no composer.json' => [null],
    'another name' => [json_encode(['name' => 'acme/app'])],
    'no JSON object' => ['{not json'],
]);
