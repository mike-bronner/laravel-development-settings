<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\PackageConfig;
use MikeBronner\DevelopmentSettings\Support\ProjectKind;
use MikeBronner\DevelopmentSettings\Support\SyncPlan;

beforeEach(function (): void {
    $this->project = makeTempDir();
    $this->package = makeTempDir();
    seedFiles($this->package, [
        'pint.json' => '{}',
        SyncPlan::MANIFEST_FILE => json_encode(['pint.json' => ['p']]),
        ProjectKind::MANIFEST_FILE => json_encode(['artisan' => ['a']]),
    ]);
    $this->config = new PackageConfig([
        'paths' => ['files' => ['pint.json'], 'managed' => ['.gitignore']],
    ]);
    $this->plan = function (): array {
        $plan = new SyncPlan($this->config, $this->package, $this->project);

        return [$plan->files(), $plan->managed(), $plan->manifest()->toArray()];
    };
});

afterEach(function (): void {
    removeTempDir($this->project);
    removeTempDir($this->package);
});

it('syncs only the tracked paths into an app', function (): void {
    seedFiles($this->project, ['artisan' => "<?php\n", ProjectKind::TESTBENCH => "<?php\n"]);

    expect(($this->plan)())->toBe([
        ['pint.json' => "{$this->package}/pint.json"],
        ['.gitignore'],
        ['pint.json' => ['p']],
    ]);
});

it('knows the shim in a package repository with Testbench, and ships it nothing', function (
    array $files,
): void {
    seedFiles($this->project, [ProjectKind::TESTBENCH => "<?php\n", ...$files]);

    expect(($this->plan)())->toBe([
        ['pint.json' => "{$this->package}/pint.json"],
        ['.gitignore'],
        ['artisan' => ['a'], 'pint.json' => ['p']],
    ]);
})->with([
    'no artisan' => [[]],
    'the shim' => [['artisan' => shimSource()]],
]);

it('leaves the shim unknown in a package repository without Testbench', function (): void {
    seedFiles($this->project, ['artisan' => shimSource()]);

    [, , $manifest] = ($this->plan)();

    expect($manifest)->toBe(['pint.json' => ['p']]);
});
