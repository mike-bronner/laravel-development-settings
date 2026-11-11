<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\LegacyFingerprint;
use MikeBronner\DevelopmentSettings\Support\PackageConfig;

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

it('keeps a keyed file the package ships out of the orphans the loaded config implies', function (
    string $local,
): void {
    $target = '.github/workflows/tia.yml';
    $options = [
        'app' => false,
        'sources' => ['resources/project/tia.yml' => "Shipped workflow\n"],
        'manifest' => [$target => [md5("Shipped workflow\n")]],
    ];
    [$this->project] = makeConsumer([
        ...$options,
        'paths' => ['files' => ['resources/project/tia.yml' => $target]],
    ]);
    seedFiles($this->project, [$target => $local]);

    $output = publishLoaded($this->project, consumerConfig($options));

    expect($output)->not
        ->toContain($target)
        ->and($output)
        ->not
        ->toContain('removed upstream')
        ->and($output)
        ->not
        ->toContain('1 removed')
        ->and(file_get_contents("{$this->project}/{$target}"))
        ->toBe($local);
})->with([
    'edited copy' => ["Our own workflow\n"],
    'unmodified copy' => ["Shipped workflow\n"],
]);

it('keeps a managed file the package ships out of the orphans the loaded config implies', function (
    string $local,
): void {
    $options = [
        'app' => false,
        'sources' => ['resources/project/gitignore' => GITIGNORE_V1],
        'manifest' => ['.gitignore' => [md5(GITIGNORE_V1)]],
    ];
    [$this->project] = makeConsumer([
        ...$options,
        'paths' => [
            'files' => ['resources/project/gitignore' => '.gitignore'],
            'managed' => ['.gitignore'],
        ],
    ]);
    seedFiles($this->project, ['.gitignore' => $local]);

    $output = publishLoaded($this->project, consumerConfig($options));

    expect($output)->not
        ->toContain('.gitignore')
        ->and($output)
        ->not
        ->toContain('1 removed')
        ->and(file_get_contents("{$this->project}/.gitignore"))
        ->toBe($local);
})->with([
    'nothing below the marker' => [marked(GITIGNORE_V1)],
    'project lines below the marker' => [marked(GITIGNORE_V1, "\n/storage\n")],
]);

it('removes a retired file although the package repository keeps its own copy', function (): void {
    $retired = '.github/ISSUE_TEMPLATE/retired.md';
    [$this->project, $package] = makeConsumer([
        'app' => false,
        'sources' => [$retired => "Shipped\n"],
        'manifest' => [$retired => [md5("Shipped\n")]],
    ]);
    seedFiles($this->project, [$retired => "Shipped\n"]);

    $output = publishIn($this->project);

    expect($output)->toContain($retired, '1 removed')
        ->and("{$this->project}/{$retired}")
        ->not
        ->toBeFile()
        ->and("{$package}/{$retired}")
        ->toBeFile();
});

it('still removes a file the package no longer ships', function (): void {
    $retired = '.github/ISSUE_TEMPLATE/retired.md';
    [$this->project] = makeConsumer([
        'app' => false,
        'manifest' => [$retired => [md5("Shipped\n")]],
    ]);
    seedFiles($this->project, [$retired => "Shipped\n"]);

    $output = publishIn($this->project);

    expect($output)->toContain($retired, '1 removed')
        ->and("{$this->project}/{$retired}")
        ->not
        ->toBeFile();
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
