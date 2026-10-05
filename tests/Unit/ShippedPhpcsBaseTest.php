<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\FileSync;

afterEach(function (): void {
    removeTempDir($this->project);
});

it('ships phpcs.xml and never a ruleset copy', function (): void {
    $this->project = makeTempDir();
    $targets = array_keys(shippedFiles('paths'));

    expect($targets)->toContain('phpcs.xml')
        ->not
        ->toContain('phpcs.xml.dist');
    $rulesetCopies = collect($targets)
        ->filter(fn (string $path): bool => str_starts_with($path, '.php-codesniffer'));

    expect($rulesetCopies)->toBeEmpty();
});

it('checks each project file with CleanCode, but not the generated directories', function (
    array $shipped,
    array $skipped,
): void {
    $this->project = phpcsProject([...$shipped, ...$skipped]);

    $checked = runPhpcs($this->project);

    expect(array_keys($checked))->toBe(collect($shipped)->sort()->values()->all());
    expect(collect($checked)->reject(fn (array $sources): bool => in_array(
            PHPCS_ELSE_SNIFF,
            $sources,
            strict: true,
        ))->all())->toBe([]);
})->with([
    'application' => [
        [
            'app/Models/User.php', 'config/app.php', 'database/seeders/DatabaseSeeder.php',
            'resources/views/home.blade.php', 'routes/web.php', 'tests/Feature/HomeTest.php',
        ],
        [
            'bootstrap/cache/services.php', 'node_modules/tool/index.php', 'public/index.php',
            'storage/framework/views/compiled.php', 'vendor/acme/lib/Lib.php',
        ],
    ],
    'package' => [['src/Service.php', 'tests/Unit/ServiceTest.php'], ['vendor/acme/lib/Lib.php']],
    'nested names, anchored on the project root' => [
        ['app/Http/public/Page.php', 'src/storage/Disk.php', 'src/vendor/Vendor.php'],
        [],
    ],
]);

/*
 * Releases before 0.3.3 shipped phpcs.xml pointing at a ruleset that no longer
 * ships. Their unmodified copies are known versions, so the sync replaces them
 * with the current file instead of keeping them as local edits.
 */
it('replaces an unmodified phpcs.xml from an older release', function (): void {
    $this->project = makeTempDir();
    file_put_contents("{$this->project}/phpcs.xml", <<<XML
        <?xml version="1.0"?>
        <ruleset>
            <rule ref="./.php-codesniffer/MikeBronner/ruleset.xml" />
        </ruleset>

        XML);

    $scan = (new FileSync(shippedManifest()))
        ->classify($this->project, ['phpcs.xml' => REPOSITORY_ROOT . '/phpcs.xml']);

    expect(array_keys(data_get($scan, 'updatable')))->toBe(['phpcs.xml']);
});

it('still knows the first phpcs.xml version the manifest recorded', function (): void {
    $this->project = makeTempDir();

    expect(shippedManifest()->isKnown('phpcs.xml', '992e3c5d1d7863fcf21d73374254a46a'))->toBeTrue();
});
