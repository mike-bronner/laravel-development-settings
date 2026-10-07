<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\ProjectKind;

beforeEach(function (): void {
    $this->project = makeTempDir('devset-repository-');
    $this->kind = new ProjectKind;
});

afterEach(function (): void {
    removeTempDir($this->project);
});

it('tells an app by an artisan that carries no shim mark', function (string $artisan): void {
    file_put_contents("{$this->project}/artisan", $artisan);

    expect($this->kind->isApp($this->project))->toBeTrue();
})->with([
    'an app artisan' => ["#!/usr/bin/env php\n<?php\n"],
    'an app that boots Testbench itself' => [
        "#!/usr/bin/env php\n<?php\n\nrequire __DIR__ . '/vendor/bin/testbench';\n",
    ],
    'the constant in a comment' => ["<?php\n\n// " . ProjectKind::SHIM_CONSTANT . "\n"],
    'the constant in a docblock' => ["<?php\n\n/** " . ProjectKind::SHIM_CONSTANT . " */\n"],
    'the constant in a string' => ["<?php\n\necho '" . ProjectKind::SHIM_CONSTANT . "';\n"],
    'the constant alone, outside PHP' => [ProjectKind::SHIM_CONSTANT],
    'the old marker inside a longer line' => ['// See: ' . ProjectKind::LEGACY_SHIM_MARKER . "\n"],
]);

it('tells no app where there is no artisan, or it is the shim', function (?string $artisan): void {
    seedFiles($this->project, collect(['artisan' => $artisan])->whereNotNull()->all());

    expect($this->kind->isApp($this->project))->toBeFalse();
})->with([
    'no artisan' => [null],
    'the shim' => [shimSource()],
    'the shim with CRLF endings' => [str_replace("\n", "\r\n", shimSource())],
    'the shim shipped from 0.3.4' => [previouslyShippedShim()],
    'the shim shipped from 0.3.4, edited' => [previouslyShippedShim() . "// Mine.\n"],
    'the shim shipped from 0.3.4, comments stripped' => [
        strippedShim(),
    ],
]);

it('tells the shim by each mark alone, with no shipped checksum', function (string $artisan): void {
    $kind = new ProjectKind(manifestFile: "{$this->project}/missing.json");

    expect($kind->isShim($artisan))->toBeTrue();
})->with([
    'the constant' => [shimSource()],
    'the old marker comment' => [previouslyShippedShim()],
    'the old marker with CRLF endings' => [str_replace("\n", "\r\n", previouslyShippedShim())],
]);

it('does not tell the stripped copy without its shipped checksum', function (): void {
    $kind = new ProjectKind(manifestFile: "{$this->project}/missing.json");

    expect($kind->isShim(strippedShim()))->toBeFalse();
});

it('tells the shim by a checksum shipped for artisan, and no other path', function (): void {
    seedFiles($this->project, [
        'artisan.json' => json_encode([ProjectKind::ARTISAN => [md5("<?php\n")]]),
        'other.json' => json_encode(['.gitattributes' => [md5("<?php\n")]]),
    ]);

    expect([
        (new ProjectKind(manifestFile: "{$this->project}/artisan.json"))->isShim("<?php\n"),
        (new ProjectKind(manifestFile: "{$this->project}/other.json"))->isShim("<?php\n"),
    ])->toBe([true, false]);
});

it('treats an artisan it cannot read as the shim as an app', function (string $shape): void {
    file_put_contents("{$this->project}/elsewhere", shimSource());
    seedFiles($this->project, [ProjectKind::TESTBENCH => "<?php\n"]);

    match ($shape) {
        'symlink to the shim' => symlink("{$this->project}/elsewhere", "{$this->project}/artisan"),
        'dangling symlink' => symlink("{$this->project}/missing", "{$this->project}/artisan"),
        'directory' => mkdir("{$this->project}/artisan"),
    };

    expect($this->kind->isApp($this->project))->toBeTrue()
        ->and($this->kind->receivesShim($this->project))
        ->toBeFalse()
        ->and($this->kind->composesBoost($this->project))
        ->toBeTrue();
})->with(['symlink to the shim', 'dangling symlink', 'directory']);

it('sends the shim only where there is no app and Testbench is installed', function (
    array $files,
    array $expected,
): void {
    seedFiles($this->project, $files);

    expect([
        'receives shim' => $this->kind->receivesShim($this->project),
        'has Testbench' => $this->kind->hasTestbench($this->project),
        'composes' => $this->kind->composesBoost($this->project),
    ])->toBe($expected);
})->with([
    'package with Testbench' => [
        [ProjectKind::TESTBENCH => "<?php\n"],
        ['receives shim' => true, 'has Testbench' => true, 'composes' => true],
    ],
    'package holding the shim' => [
        ['artisan' => shimSource(), ProjectKind::TESTBENCH => "<?php\n"],
        ['receives shim' => true, 'has Testbench' => true, 'composes' => true],
    ],
    'package without Testbench' => [
        [],
        ['receives shim' => false, 'has Testbench' => false, 'composes' => false],
    ],
    'app with Testbench' => [
        ['artisan' => "<?php\n", ProjectKind::TESTBENCH => "<?php\n"],
        ['receives shim' => false, 'has Testbench' => true, 'composes' => true],
    ],
    'app booting Testbench itself' => [
        [
            'artisan' => "<?php\n\nrequire __DIR__ . '/vendor/bin/testbench';\n",
            ProjectKind::TESTBENCH => "<?php\n",
        ],
        ['receives shim' => false, 'has Testbench' => true, 'composes' => true],
    ],
]);
