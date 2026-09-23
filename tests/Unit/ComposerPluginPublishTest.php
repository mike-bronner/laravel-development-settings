<?php

declare(strict_types=1);

use Composer\IO\BufferIO;
use MikeBronner\DevelopmentSettings\ComposerPlugin;
use MikeBronner\DevelopmentSettings\Support\ContributionDetector;
use MikeBronner\DevelopmentSettings\Support\GuidelineGuard;
use MikeBronner\DevelopmentSettings\Support\LegacyFingerprint;

/*
 * These tests drive `doPublish()`, the whole Composer hook, against a consuming
 * project on disk. The package it installs from is a real directory under
 * `vendor/mikebronner/development-settings` whose config names no dependencies
 * (so no `composer update` is ever spawned) and whose Boost command is a stand-in.
 */

const COMPOSES = 'composes';
const COMPOSES_NOTHING = 'composes nothing';
const EXITS_WITH_ERROR = 'exits with an error';

/**
 * The Boost command stand-in. It records having run, then does what Boost
 * does in the named case: write a composed block into `AGENTS.md`, write
 * nothing and exit 0 (no agent found), or exit non-zero.
 */
function boostStandIn(string $behaviour): string
{
    $block = GuidelineGuard::OPENING_TAG . "\n=== rules ===\n" . GuidelineGuard::CLOSING_TAG . "\n";

    $script = match ($behaviour) {
        COMPOSES => 'touch("boost.ran"); file_put_contents("AGENTS.md", ' . var_export($block, true) . ');',
        COMPOSES_NOTHING => 'touch("boost.ran");',
        EXITS_WITH_ERROR => 'touch("boost.ran"); exit(1);',
    };

    return escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script);
}

/**
 * A consuming project with the package installed in vendor.
 *
 * @param  array{app?: bool, boostInstalled?: bool, boost?: string, manifest?: array<string, list<string>>, sources?: array<string, string>, captured?: array<string, list<string>>}  $options
 * @return array{0: string, 1: string} project dir, package dir
 */
function makeConsumer(array $options = []): array
{
    $project = makeTempDir('devset-publish-');
    $package = $project . '/vendor/mikebronner/development-settings';

    mkdir($package . '/config', 0755, true);
    file_put_contents($project . '/composer.json', json_encode(['require-dev' => new stdClass]) . "\n");

    $config = [
        'composer' => ['install' => [], 'remove' => []],
        'hooks' => [
            'command' => boostStandIn($options['boost'] ?? COMPOSES),
            'description' => 'Composing Laravel Boost guidelines and skills...',
        ],
        'capture' => ['resources/boost'],
        'paths' => [
            'directories' => [],
            'files' => [],
            'legacy_symlinks' => ['.ai'],
            'ignore' => ['.DS_Store'],
        ],
    ];

    file_put_contents($package . '/config/development-settings.php', '<?php return ' . var_export($config, true) . ';');
    file_put_contents($package . '/manifest.json', json_encode($options['manifest'] ?? new stdClass) . "\n");
    file_put_contents($package . '/' . ContributionDetector::MANIFEST_FILE, json_encode($options['captured'] ?? new stdClass) . "\n");

    foreach ($options['sources'] ?? [] as $relativePath => $content) {
        if (! is_dir(dirname($package . '/' . $relativePath))) {
            mkdir(dirname($package . '/' . $relativePath), 0755, true);
        }

        file_put_contents($package . '/' . $relativePath, $content);
    }

    if ($options['app'] ?? true) {
        file_put_contents($project . '/artisan', "#!/usr/bin/env php\n");
    }

    if ($options['boostInstalled'] ?? true) {
        mkdir($project . '/vendor/laravel/boost', 0755, true);
    }

    return [$project, $package];
}

function publishIn(string $project, string $hook = 'doPublish'): string
{
    $io = new BufferIO;
    $cwd = (string) getcwd();

    chdir($project);

    try {
        (new ReflectionMethod(ComposerPlugin::class, $hook))->invoke(new ComposerPlugin, $io);
    } finally {
        chdir($cwd);
    }

    return $io->getOutput();
}

function boostRan(string $project): bool
{
    return file_exists($project . '/boost.ran');
}

function boostConfigIn(string $project): array
{
    return json_decode((string) file_get_contents($project . '/boost.json'), associative: true);
}

it('registers the package and composes in a fresh clone that has no boost.json', function (): void {
    [$project] = makeConsumer();

    $output = publishIn($project);

    expect(boostConfigIn($project))->toBe(['packages' => ['mikebronner/development-settings']])
        ->and($output)->toContain('boost.json (registered with Boost)')
        ->and($output)->toContain('1 new')
        ->and(boostRan($project))->toBeTrue()
        ->and($output)->toContain('Composing Laravel Boost guidelines and skills... done');

    removeTempDir($project);
});

it('fails loudly when Boost exits cleanly on a registrar-created boost.json but composes nothing', function (): void {
    [$project] = makeConsumer(['boost' => COMPOSES_NOTHING]);

    $output = publishIn($project);

    // The F1 case: the file the plugin wrote enables no guidelines, no skills
    // and no agents, and Boost answers that with a successful exit.
    expect(boostRan($project))->toBeTrue()
        ->and($output)->toContain('names no agents')
        ->and($output)->toContain('Composing Laravel Boost guidelines and skills... failed')
        ->and($output)->toContain('composed no agent file')
        ->and($output)->not->toContain('done');

    removeTempDir($project);
});

it('does not count a composed block that was already on disk before the run', function (): void {
    [$project] = makeConsumer(['boost' => COMPOSES_NOTHING]);

    // The Laravel skeleton ships a CLAUDE.md that already holds a block.
    file_put_contents(
        $project . '/CLAUDE.md',
        GuidelineGuard::OPENING_TAG . "\nInstall Boost.\n" . GuidelineGuard::CLOSING_TAG . "\n",
    );
    touch($project . '/CLAUDE.md', time() - 60);

    $output = publishIn($project);

    expect($output)->toContain('composed no agent file')
        ->and($output)->not->toContain('done');

    removeTempDir($project);
});

it('reports a Boost error as a failure with the next step', function (): void {
    [$project] = makeConsumer(['boost' => EXITS_WITH_ERROR]);

    $output = publishIn($project);

    expect(boostRan($project))->toBeTrue()
        ->and($output)->toContain('... failed')
        ->and($output)->toContain('Laravel Boost exited with an error')
        ->and($output)->not->toContain('composed no agent file');

    removeTempDir($project);
});

it('does not warn about agents when boost.json names them', function (): void {
    [$project] = makeConsumer();
    file_put_contents($project . '/boost.json', json_encode([
        'agents' => ['claude_code'],
        'packages' => ['mikebronner/development-settings'],
    ]));

    $output = publishIn($project);

    expect($output)->not->toContain('names no agents')
        ->and($output)->not->toContain('registered with Boost')
        ->and($output)->toContain('... done');

    removeTempDir($project);
});

it('neither registers nor composes in a package, which has no artisan', function (): void {
    [$project] = makeConsumer(['app' => false]);

    $output = publishIn($project);

    expect(file_exists($project . '/boost.json'))->toBeFalse()
        ->and(boostRan($project))->toBeFalse()
        ->and($output)->not->toContain('registered with Boost')
        ->and($output)->not->toContain('Composing Laravel Boost');

    removeTempDir($project);
});

it('does not run Boost over a boost.json it cannot read, and says so', function (): void {
    [$project] = makeConsumer();
    file_put_contents($project . '/boost.json', '{not json');

    $output = publishIn($project);

    // boost:install would treat the file as empty and write a fresh config
    // over the developer's own settings.
    expect(boostRan($project))->toBeFalse()
        ->and(file_get_contents($project . '/boost.json'))->toBe('{not json')
        ->and($output)->toContain('boost.json is not valid JSON, so Laravel Boost was not run')
        ->and($output)->toContain('1 skipped');

    removeTempDir($project);
});

it('stays quiet about Boost while it is still queued for installation, but still registers', function (): void {
    [$project] = makeConsumer(['boostInstalled' => false]);

    $output = publishIn($project);

    expect(boostConfigIn($project))->toBe(['packages' => ['mikebronner/development-settings']])
        ->and(boostRan($project))->toBeFalse()
        ->and($output)->not->toContain('Composing Laravel Boost');

    removeTempDir($project);
});

it('removes the legacy .ai link and leaves the package sources it pointed at intact', function (): void {
    $source = "# Identity\n";
    [$project, $package] = makeConsumer([
        'manifest' => ['.ai/guidelines/01-identity.md' => [md5($source)]],
    ]);

    mkdir($package . '/.ai/guidelines', 0755, true);
    file_put_contents($package . '/.ai/guidelines/01-identity.md', $source);
    symlink('vendor/mikebronner/development-settings/.ai', $project . '/.ai');

    $output = publishIn($project);

    // While the link stands, the manifest's `.ai/…` path resolves into vendor
    // and would read as an unmodified orphan to delete.
    expect(is_link($project . '/.ai'))->toBeFalse()
        ->and(file_get_contents($package . '/.ai/guidelines/01-identity.md'))->toBe($source)
        ->and($output)->toContain('.ai (stale symlink into vendor)')
        ->and($output)->not->toContain('01-identity.md');

    removeTempDir($project);
});

it('removes the fingerprint file earlier releases wrote and reports it', function (): void {
    [$project] = makeConsumer(['app' => false]);
    file_put_contents($project . '/' . LegacyFingerprint::FILE, md5('sources') . "\n");

    $output = publishIn($project);

    expect(file_exists($project . '/' . LegacyFingerprint::FILE))->toBeFalse()
        ->and($output)->toContain('.dev-settings-boost (stale Boost fingerprint)')
        ->and($output)->toContain('1 removed');

    removeTempDir($project);
});

it('names edited guideline sources and the contribute command before an update', function (): void {
    [$project, $package] = makeConsumer([
        'sources' => [
            'resources/boost/guidelines/01-identity.md' => "# Identity, fixed in this project\n",
            'resources/boost/guidelines/02-workflow.md' => "# Workflow\n",
        ],
        'captured' => [
            'resources/boost/guidelines/01-identity.md' => [md5("# Identity\n")],
            'resources/boost/guidelines/02-workflow.md' => [md5("# Workflow\n")],
        ],
    ]);

    $output = publishIn($project, 'doCapture');

    // BufferIO is non-interactive, so the capture names the edits and the
    // command, and opens nothing.
    expect($output)->toContain('1 local edit(s) to shared development-settings files')
        ->and($output)->toContain('resources/boost/guidelines/01-identity.md')
        ->and($output)->not->toContain('02-workflow.md')
        ->and($output)->toContain('vendor/bin/dev-settings-contribute.php')
        ->and(file_get_contents($package . '/resources/boost/guidelines/01-identity.md'))->toBe("# Identity, fixed in this project\n");

    removeTempDir($project);
});

it('stays silent before an update when no installed source was edited', function (): void {
    [$project] = makeConsumer([
        'sources' => ['resources/boost/guidelines/01-identity.md' => "# Identity\n"],
        'captured' => ['resources/boost/guidelines/01-identity.md' => [md5("# Identity\n")]],
    ]);

    expect(publishIn($project, 'doCapture'))->toBe('');

    removeTempDir($project);
});

it("never treats a consuming package's own resources/boost files as orphans", function (): void {
    $shipped = "# Identity\n";
    [$project] = makeConsumer([
        'app' => false,
        'sources' => ['resources/boost/guidelines/01-identity.md' => $shipped],
        'captured' => ['resources/boost/guidelines/01-identity.md' => [md5($shipped)]],
    ]);

    // A consumer that publishes its own Boost guidelines, holding a file at the
    // same path and with the same content as one this package ships.
    mkdir($project . '/resources/boost/guidelines', 0755, true);
    file_put_contents($project . '/resources/boost/guidelines/01-identity.md', $shipped);

    publishIn($project);

    expect(file_get_contents($project . '/resources/boost/guidelines/01-identity.md'))->toBe($shipped);

    removeTempDir($project);
});

it('keeps capture checksums out of the manifest copy-sync and orphan cleanup read', function (): void {
    $root = dirname(__DIR__, 2);
    $config = require $root . '/config/development-settings.php';
    $manifestKeys = array_keys(json_decode((string) file_get_contents($root . '/manifest.json'), associative: true));
    $captureKeys = array_keys(json_decode((string) file_get_contents($root . '/' . ContributionDetector::MANIFEST_FILE), associative: true));

    $underCapture = fn (string $key): bool => array_filter(
        $config['capture'],
        fn (string $directory): bool => str_starts_with($key, $directory . '/'),
    ) !== [];

    expect(array_filter($manifestKeys, $underCapture))->toBe([])
        ->and($captureKeys)->not->toBe([])
        ->and(array_filter($captureKeys, fn (string $key): bool => ! $underCapture($key)))->toBe([]);
});

it('captures before an update and publishes after install and update', function (): void {
    expect(ComposerPlugin::getSubscribedEvents())->toBe([
        'pre-update-cmd' => 'captureBeforeUpdate',
        'post-install-cmd' => 'publish',
        'post-update-cmd' => 'publish',
    ]);
});
