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
        'resources/project/artisan' => shimSource(),
        SyncPlan::MANIFEST_FILE => json_encode(['pint.json' => ['p']]),
        ProjectKind::MANIFEST_FILE => json_encode(['artisan' => ['a']]),
    ]);
    $this->config = new PackageConfig([
        'paths' => ['files' => ['pint.json'], 'managed' => ['.gitignore']],
        'package' => [
            'files' => ['resources/project/artisan' => 'artisan'],
            'managed' => ['artisan'],
        ],
    ]);
});

afterEach(function (): void {
    removeTempDir($this->project);
    removeTempDir($this->package);
});

it('syncs only the tracked paths into an app', function (): void {
    seedFiles($this->project, ['artisan' => "<?php\n", ProjectKind::TESTBENCH => "<?php\n"]);

    $plan = new SyncPlan($this->config, $this->package, $this->project);

    expect([$plan->files(), $plan->managed(), $plan->manifest()->toArray()])->toBe([
        ['pint.json' => "{$this->package}/pint.json"],
        ['.gitignore'],
        ['pint.json' => ['p']],
    ]);
});

it('adds the package files to a package repository with Testbench', function (): void {
    seedFiles($this->project, [ProjectKind::TESTBENCH => "<?php\n"]);

    $plan = new SyncPlan($this->config, $this->package, $this->project);

    expect([$plan->files(), $plan->managed(), $plan->manifest()->toArray()])->toBe([
        [
            'pint.json' => "{$this->package}/pint.json",
            'artisan' => "{$this->package}/resources/project/artisan",
        ],
        ['.gitignore', 'artisan'],
        ['artisan' => ['a'], 'pint.json' => ['p']],
    ]);
});
