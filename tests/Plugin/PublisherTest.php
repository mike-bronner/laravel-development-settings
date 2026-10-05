<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\LegacyFingerprint;
use MikeBronner\DevelopmentSettings\Support\PackageConfig;

/*
 * These tests drive a whole publish, the Composer hook after an install or an
 * update, against a consuming project on disk: see makeConsumer(). The shim,
 * the Boost run and the managed .gitignore have test files of their own.
 */

const REGISTERED_PACKAGES = ['packages' => ['mike-bronner/laravel-development-settings']];

afterEach(function (): void {
    removeTempDir($this->project);
});

it('registers the package and composes in a fresh clone with no boost.json', function (): void {
    [$this->project] = makeConsumer();

    $output = publishIn($this->project);

    expect(boostConfigIn($this->project))->toBe(REGISTERED_PACKAGES);
    expect(boostRan($this->project))->toBeTrue();
    expect($output)->toContain(
            'boost.json (registered with Boost)',
            '1 new',
            'Composing Laravel Boost guidelines and skills... done',
        );
    expect($output)->not
        ->toContain('Boost stand-in');
});

it('does not warn about agents when boost.json names them', function (): void {
    [$this->project] = makeConsumer();
    file_put_contents("{$this->project}/boost.json", json_encode([
        'agents' => ['claude_code'],
        ...REGISTERED_PACKAGES,
    ]));

    $output = publishIn($this->project);

    expect($output)->not
        ->toContain('names no agents', 'registered with Boost');
    expect($output)->toContain('... done');
});

it('replaces the pre-rename package name in boost.json', function (): void {
    [$this->project] = makeConsumer();
    file_put_contents("{$this->project}/boost.json", json_encode([
        'agents' => ['claude_code'],
        'packages' => ['acme/other', 'mikebronner/development-settings'],
    ]));

    $output = publishIn($this->project);

    expect(boostConfigIn($this->project))->toBe([
        'agents' => ['claude_code'],
        'packages' => ['acme/other', 'mike-bronner/laravel-development-settings'],
    ]);
    expect($output)->toContain('boost.json (registered with Boost)');
});

it('does not run Boost over a boost.json it cannot read, and says so', function (): void {
    [$this->project] = makeConsumer();
    file_put_contents("{$this->project}/boost.json", '{not json');

    $output = publishIn($this->project);

    expect([boostRan($this->project), file_get_contents("{$this->project}/boost.json")])
        ->toBe([false, '{not json']);
    expect($output)
        ->toContain('boost.json is not valid JSON, so Laravel Boost was not run', '1 skipped');
});

it("leaves the project's composer.json exactly as it found it", function (): void {
    [$this->project] = makeConsumer();
    $composerJson = json_encode(['require-dev' => ['laravel/pint' => '^1.0']], JSON_PRETTY_PRINT);
    file_put_contents("{$this->project}/composer.json", $composerJson);

    $output = publishIn($this->project);

    expect(file_get_contents("{$this->project}/composer.json"))->toBe($composerJson);
    expect($output)->not
        ->toContain('(composer)', 'Dev dependencies');
    expect(require REPOSITORY_ROOT . '/' . PackageConfig::FILE)->not
        ->toHaveKey('composer');
});

it('removes a legacy .ai link, and leaves what it points at', function (string $target): void {
    [$this->project, $package] = makeConsumer([
        'manifest' => ['.ai/guidelines/01-identity.md' => [md5("Identity\n")]],
        'sources' => ['.ai/guidelines/01-identity.md' => "Identity\n"],
    ]);
    symlink($target, "{$this->project}/.ai");

    $output = publishIn($this->project);

    expect(is_link("{$this->project}/.ai"))->toBeFalse();
    expect(file_get_contents("{$package}/.ai/guidelines/01-identity.md"))->toBe("Identity\n");
    expect($output)->toContain('.ai (stale symlink into vendor)');
    expect($output)->not
        ->toContain('01-identity.md');
})->with([
    'a link into the package' => ['vendor/mike-bronner/laravel-development-settings/.ai'],
    'a dangling link into the pre-rename path' => ['vendor/mikebronner/development-settings/.ai'],
]);

it('removes the fingerprint file earlier releases wrote, and reports it', function (): void {
    [$this->project] = makeConsumer(['app' => false]);
    file_put_contents("{$this->project}/" . LegacyFingerprint::FILE, md5('sources') . "\n");

    $output = publishIn($this->project);

    expect(file_exists("{$this->project}/" . LegacyFingerprint::FILE))->toBeFalse();
    expect($output)->toContain('.dev-settings-boost (stale Boost fingerprint)', '1 removed');
});

it("never treats a consuming package's own resources/boost as orphans", function (): void {
    [$this->project] = makeConsumer([
        'app' => false,
        'sources' => ['resources/boost/guidelines/01-identity.md' => "Identity\n"],
        'captured' => ['resources/boost/guidelines/01-identity.md' => [md5("Identity\n")]],
    ]);
    seedFiles($this->project, ['resources/boost/guidelines/01-identity.md' => "Identity\n"]);

    publishIn($this->project);

    expect(file_get_contents("{$this->project}/resources/boost/guidelines/01-identity.md"))
        ->toBe("Identity\n");
});

it('reports a new file it cannot create as failed, not created, and carries on', function (): void {
    [$this->project] = makeConsumer([
        'app' => false,
        'sources' => ['a.yml' => "a\n", 'b.yml' => "b\n"],
        'paths' => ['files' => ['a.yml' => 'locked/a.yml', 'b.yml']],
    ]);
    mkdir("{$this->project}/locked", MODE_LOCKED_DIRECTORY);

    $output = publishIn($this->project);
    chmod("{$this->project}/locked", TEMP_DIR_PERMISSIONS);

    expect(file_exists("{$this->project}/locked/a.yml"))->toBeFalse();
    expect(file_get_contents("{$this->project}/b.yml"))->toBe("b\n");
    expect($output)->toContain(
            'locked/a.yml (write failed)',
            'locked/a.yml was not updated. Could not copy',
            '1 new',
            '1 skipped',
        );
});
