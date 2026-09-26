<?php

declare(strict_types=1);

use Composer\IO\BufferIO;
use Laravel\Prompts\Key;
use Laravel\Prompts\Output\BufferedConsoleOutput;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\Terminal;
use MikeBronner\DevelopmentSettings\ComposerPlugin;
use MikeBronner\DevelopmentSettings\Support\ContributionDetector;
use MikeBronner\DevelopmentSettings\Support\GuidelineGuard;
use MikeBronner\DevelopmentSettings\Support\LegacyFingerprint;
use MikeBronner\DevelopmentSettings\Support\ManagedSection;
use MikeBronner\DevelopmentSettings\Support\PackageRepository;
use Symfony\Component\Console\Output\ConsoleOutput;

/*
 * These tests drive `doPublish()`, the whole Composer hook, against a consuming
 * project on disk. The package it installs from is a real directory under
 * `vendor/mike-bronner/laravel-development-settings` whose config names no dependencies
 * (so no `composer update` is ever spawned) and whose Boost command is a stand-in.
 * It ships this repository's own artisan shim and .gitattributes sources.
 */

const COMPOSES = 'composes';
const COMPOSES_NOTHING = 'composes nothing';
const EXITS_WITH_ERROR = 'exits with an error';

// The Boost command stand-in, as PHP code. It records having run, the rooting
// it saw and the arguments it was given, then does what Boost does in the
// named case: write a composed block into `AGENTS.md`, write nothing and exit
// 0 (no agent found), or exit non-zero.
function boostScript(string $behaviour): string
{
    $block = GuidelineGuard::OPENING_TAG . "\n=== rules ===\n" . GuidelineGuard::CLOSING_TAG . "\n";
    $record = 'file_put_contents("boost.ran", json_encode(["APP_BASE_PATH" => $_ENV["APP_BASE_PATH"] ?? null, "APP_ENV" => $_ENV["APP_ENV"] ?? null, "TESTBENCH_WORKING_PATH" => getenv("TESTBENCH_WORKING_PATH"), "directories" => is_dir("bootstrap/cache") && is_dir("storage/framework/views"), "arguments" => array_slice($argv, 1)]));';

    return $record . match ($behaviour) {
        COMPOSES => ' file_put_contents("AGENTS.md", ' . var_export($block, true) . '); echo "Boost stand-in composed AGENTS.md\n";',
        COMPOSES_NOTHING => ' echo "Boost stand-in found no agent\n";',
        EXITS_WITH_ERROR => ' fwrite(STDERR, "Boost stand-in: <error>command \\"boost:install\\"</error> is not defined\n"); exit(1);',
    };
}

// The Boost command, run straight through PHP. The trailing `--` hands an
// appended flag to the script, not to PHP.
function boostStandIn(string $behaviour): string
{
    return escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg(boostScript($behaviour)) . ' --';
}

// A consuming project with the package installed in vendor. `command`
// replaces the Boost command, `packageManifest` the shipped package-manifest.json,
// and `testbench` installs a Testbench that runs the Boost stand-in.
function makeConsumer(array $options = []): array
{
    $project = makeTempDir('devset-publish-');
    $package = $project . '/vendor/mike-bronner/laravel-development-settings';

    mkdir($package . '/config', 0755, true);
    file_put_contents($project . '/composer.json', json_encode(['require-dev' => new stdClass]) . "\n");

    $config = [
        'composer' => ['install' => [], 'remove' => []],
        'hooks' => [
            'command' => $options['command'] ?? boostStandIn($options['boost'] ?? COMPOSES),
            'description' => 'Composing Laravel Boost guidelines and skills...',
        ],
        'package' => (require dirname(__DIR__, 2) . '/config/development-settings.php')['package'],
        'capture' => ['resources/boost'],
        'paths' => [
            'directories' => [],
            'files' => [],
            'legacy_symlinks' => ['.ai'],
            'ignore' => ['.DS_Store'],
            ...$options['paths'] ?? [],
        ],
    ];

    file_put_contents($package . '/config/development-settings.php', '<?php return ' . var_export($config, true) . ';');
    file_put_contents($package . '/manifest.json', json_encode($options['manifest'] ?? new stdClass) . "\n");
    file_put_contents($package . '/' . ContributionDetector::MANIFEST_FILE, json_encode($options['captured'] ?? new stdClass) . "\n");
    file_put_contents($package . '/' . PackageRepository::MANIFEST_FILE, json_encode($options['packageManifest'] ?? shippedPackageManifest()) . "\n");

    foreach (array_keys($config['package']['files']) as $source) {
        if (! is_dir(dirname($package . '/' . $source))) {
            mkdir(dirname($package . '/' . $source), 0755, true);
        }

        copy(dirname(__DIR__, 2) . '/' . $source, $package . '/' . $source);
    }

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

    if ($options['testbench'] ?? false) {
        mkdir($project . '/vendor/bin', 0755, true);
        file_put_contents($project . '/' . PackageRepository::TESTBENCH, "<?php\n" . boostScript($options['boost'] ?? COMPOSES) . "\n");
    }

    return [$project, $package];
}

function publishIn(string $project, string $hook = 'doPublish', bool $interactive = false): string
{
    $io = new BufferIO;

    if ($interactive) {
        $io->setUserInputs([]);
    }

    $cwd = (string) getcwd();

    chdir($project);

    try {
        (new ReflectionMethod(ComposerPlugin::class, $hook))->invoke(new ComposerPlugin, $io);
    } finally {
        chdir($cwd);
    }

    return $io->getOutput();
}

// The package manifest as this repository ships it: every current source known.
function shippedPackageManifest(): array
{
    return json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/' . PackageRepository::MANIFEST_FILE), associative: true);
}

function shimSource(): string
{
    return (string) file_get_contents(dirname(__DIR__, 2) . '/resources/project/artisan');
}

function boostRan(string $project): bool
{
    return file_exists($project . '/boost.ran');
}

function boostRun(string $project): array
{
    return json_decode((string) file_get_contents($project . '/boost.ran'), associative: true);
}

function boostConfigIn(string $project): array
{
    return json_decode((string) file_get_contents($project . '/boost.json'), associative: true);
}

it('registers the package and composes in a fresh clone that has no boost.json', function (): void {
    [$project] = makeConsumer();

    $output = publishIn($project);

    expect(boostConfigIn($project))->toBe(['packages' => ['mike-bronner/laravel-development-settings']])
        ->and($output)->toContain('boost.json (registered with Boost)')
        ->and($output)->toContain('1 new')
        ->and(boostRan($project))->toBeTrue()
        ->and($output)->toContain('Composing Laravel Boost guidelines and skills... done')
        // A successful run stays one summary line: Boost's own output is not shown.
        ->and($output)->not->toContain('Boost stand-in');

    removeTempDir($project);
});

it('composes an app through its own artisan, and gives it no package file, even with Testbench installed', function (): void {
    [$project] = makeConsumer(['testbench' => true]);
    file_put_contents($project . '/.gitattributes', "* text=auto\n");

    $output = publishIn($project);

    expect(boostRun($project)['arguments'])->toBe(['--guidelines', '--skills', '--mcp'])
        ->and(boostRun($project)['APP_BASE_PATH'])->toBeNull()
        ->and(file_get_contents($project . '/artisan'))->toBe("#!/usr/bin/env php\n")
        ->and(file_get_contents($project . '/.gitattributes'))->toBe("* text=auto\n")
        ->and($output)->not->toMatch('/  (artisan|\.gitattributes)\b/')
        ->and(is_dir($project . '/bootstrap'))->toBeFalse()
        ->and(is_dir($project . '/storage'))->toBeFalse();

    removeTempDir($project);
});

// The package manifest knows `artisan`. An app's own artisan is no version of
// it, so if the manifest reached an app, copy-sync would call that file locally
// modified and orphan cleanup would offer to delete it.
it("never reports or offers to delete an app's own artisan", function (): void {
    [$project] = makeConsumer([
        'testbench' => true,
        'packageManifest' => [...shippedPackageManifest(), 'artisan' => [md5("#!/usr/bin/env php\n")], 'retired.txt' => [md5("x\n")]],
    ]);

    $output = publishIn($project);

    expect(file_get_contents($project . '/artisan'))->toBe("#!/usr/bin/env php\n")
        ->and($output)->not->toMatch('/  artisan\b/')
        ->and($output)->toContain('0 removed');

    removeTempDir($project);
});

it('writes the artisan shim and the managed .gitattributes into a package with Testbench, then composes through the shim', function (): void {
    [$project, $package] = makeConsumer(['app' => false, 'testbench' => true]);

    $output = publishIn($project);

    expect(file_get_contents($project . '/artisan'))->toBe(shimSource())
        ->and(file_get_contents($project . '/.gitattributes'))->toBe(file_get_contents($package . '/resources/project/gitattributes') . ManagedSection::MARKER . "\n")
        ->and($output)->toMatch('/\+  artisan /')
        ->and($output)->toMatch('/\+  \.gitattributes /')
        ->and(boostConfigIn($project))->toBe(['packages' => ['mike-bronner/laravel-development-settings']])
        ->and($output)->toContain('boost.json (registered with Boost)')
        ->and(boostRun($project)['arguments'])->toBe(['--guidelines', '--skills', '--mcp'])
        ->and($output)->toContain('Composing Laravel Boost guidelines and skills... done');

    removeTempDir($project);
});

// The real shim runs the real command line, and the Testbench it boots is the
// stand-in: this is the path `php artisan boost:install` takes in a package.
it('runs Boost in a package through the shim, rooted at the repository, and never roots Composer', function (): void {
    [$project] = makeConsumer([
        'app' => false,
        'testbench' => true,
        'command' => escapeshellarg(PHP_BINARY) . ' artisan boost:install --no-interaction',
    ]);

    $output = publishIn($project);

    expect(boostRun($project))->toBe([
        'APP_BASE_PATH' => realpath($project),
        'APP_ENV' => 'local',
        'TESTBENCH_WORKING_PATH' => realpath($project),
        'directories' => true,
        'arguments' => ['boost:install', '--no-interaction', '--guidelines', '--skills', '--mcp'],
    ])
        ->and($output)->toContain('... done')
        ->and($_ENV['APP_BASE_PATH'] ?? null)->toBeNull()
        ->and(getenv('TESTBENCH_WORKING_PATH'))->toBeFalse();

    removeTempDir($project);
});

it('passes Boost every feature, whatever boost.json says, in an app and in a package', function (bool $app, array $config): void {
    [$project] = makeConsumer(['app' => $app, 'testbench' => true]);
    file_put_contents($project . '/boost.json', json_encode(['agents' => ['claude_code'], ...$config]));

    publishIn($project);

    expect(boostRun($project)['arguments'])->toBe(['--guidelines', '--skills', '--mcp']);

    removeTempDir($project);
})->with([
    'app' => true,
    'package' => false,
])->with([
    'no feature keys' => [[]],
    'mcp off' => [['mcp' => false]],
    'every feature off' => [['guidelines' => false, 'skills' => false, 'mcp' => false]],
]);

it('updates a shim this package shipped before', function (): void {
    $old = str_replace('rooted at this repository.', "rooted at this repository.\n// An older release.", shimSource());
    [$project] = makeConsumer([
        'app' => false,
        'testbench' => true,
        'packageManifest' => [...shippedPackageManifest(), 'artisan' => [md5($old), md5(shimSource())]],
    ]);
    file_put_contents($project . '/artisan', $old);

    $output = publishIn($project);

    expect(file_get_contents($project . '/artisan'))->toBe(shimSource())
        ->and($output)->toMatch('/↻  artisan /')
        ->and(boostRan($project))->toBeTrue();

    removeTempDir($project);
});

it('keeps an edited shim and names it as locally modified', function (): void {
    $edited = shimSource() . "// Mine.\n";
    [$project] = makeConsumer(['app' => false, 'testbench' => true]);
    file_put_contents($project . '/artisan', $edited);

    $output = publishIn($project);

    expect(file_get_contents($project . '/artisan'))->toBe($edited)
        ->and($output)->toContain('artisan (locally modified)')
        ->and(boostRan($project))->toBeTrue();

    removeTempDir($project);
});

it('does not run Boost through a shim whose Testbench is gone, and says what is missing', function (): void {
    [$project] = makeConsumer(['app' => false]);
    file_put_contents($project . '/artisan', shimSource());

    $output = publishIn($project);

    expect(file_get_contents($project . '/artisan'))->toBe(shimSource())
        ->and(boostRan($project))->toBeFalse()
        ->and(file_exists($project . '/boost.json'))->toBeFalse()
        ->and($output)->toContain('no artisan of its own and no vendor/bin/testbench, so Laravel Boost was not run');

    removeTempDir($project);
});

it("only warns about a package's own unmarked .gitattributes in a non-interactive run", function (): void {
    [$project] = makeConsumer(['app' => false, 'testbench' => true]);
    file_put_contents($project . '/.gitattributes', "/tests export-ignore\n");

    $output = publishIn($project);

    expect(file_get_contents($project . '/.gitattributes'))->toBe("/tests export-ignore\n")
        ->and($output)->toContain('.gitattributes (locally modified, no sync marker)')
        ->and(file_get_contents($project . '/artisan'))->toBe(shimSource());

    removeTempDir($project);
});

it('names the artisan install as the next step when a package composes nothing', function (): void {
    [$project] = makeConsumer(['app' => false, 'testbench' => true, 'boost' => COMPOSES_NOTHING]);

    $output = publishIn($project);

    expect($output)->toContain('Run "php artisan boost:install" once to choose them.')
        ->and($output)->toContain('Run "php artisan boost:install" and choose your agents.');

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
        ->and($output)->toContain('│ Boost stand-in found no agent')
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

    // Boost's own words are shown, escaped, so the tag it printed survives.
    expect(boostRan($project))->toBeTrue()
        ->and($output)->toContain('... failed')
        ->and($output)->toContain('Laravel Boost exited with an error')
        ->and($output)->toContain('│ Boost stand-in: <error>command "boost:install"</error> is not defined')
        ->and($output)->not->toContain('composed no agent file');

    removeTempDir($project);
});

it('does not warn about agents when boost.json names them', function (): void {
    [$project] = makeConsumer();
    file_put_contents($project . '/boost.json', json_encode([
        'agents' => ['claude_code'],
        'packages' => ['mike-bronner/laravel-development-settings'],
    ]));

    $output = publishIn($project);

    expect($output)->not->toContain('names no agents')
        ->and($output)->not->toContain('registered with Boost')
        ->and($output)->toContain('... done');

    removeTempDir($project);
});

it('neither writes the shim, registers nor composes in a package without Testbench, and says what is missing', function (): void {
    [$project] = makeConsumer(['app' => false]);

    $output = publishIn($project);

    expect(file_exists($project . '/boost.json'))->toBeFalse()
        ->and(file_exists($project . '/artisan'))->toBeFalse()
        ->and(file_exists($project . '/.gitattributes'))->toBeFalse()
        ->and(boostRan($project))->toBeFalse()
        ->and($output)->not->toContain('registered with Boost')
        ->and($output)->not->toContain('Composing Laravel Boost')
        ->and($output)->toContain('This repository has no artisan of its own and no vendor/bin/testbench, so Laravel Boost was not run. Require orchestra/testbench as a dev dependency');

    removeTempDir($project);
});

it('stays quiet in a package without Testbench while Boost is still queued for installation', function (): void {
    [$project] = makeConsumer(['app' => false, 'boostInstalled' => false]);

    $output = publishIn($project);

    expect(boostRan($project))->toBeFalse()
        ->and($output)->not->toContain('Laravel Boost');

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

    expect(boostConfigIn($project))->toBe(['packages' => ['mike-bronner/laravel-development-settings']])
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
    symlink('vendor/mike-bronner/laravel-development-settings/.ai', $project . '/.ai');

    $output = publishIn($project);

    // While the link stands, the manifest's `.ai/…` path resolves into vendor
    // and would read as an unmodified orphan to delete.
    expect(is_link($project . '/.ai'))->toBeFalse()
        ->and(file_get_contents($package . '/.ai/guidelines/01-identity.md'))->toBe($source)
        ->and($output)->toContain('.ai (stale symlink into vendor)')
        ->and($output)->not->toContain('01-identity.md');

    removeTempDir($project);
});

it('replaces the pre-rename package name in boost.json on upgrade', function (): void {
    [$project] = makeConsumer();
    file_put_contents($project . '/boost.json', json_encode([
        'agents' => ['claude_code'],
        'packages' => ['acme/other', 'mikebronner/development-settings'],
    ]));

    $output = publishIn($project);

    expect(boostConfigIn($project))->toBe([
        'agents' => ['claude_code'],
        'packages' => ['acme/other', 'mike-bronner/laravel-development-settings'],
    ])
        ->and($output)->toContain('boost.json (registered with Boost)');

    removeTempDir($project);
});

it('removes a dangling .ai link into the pre-rename vendor path on upgrade', function (): void {
    [$project] = makeConsumer();

    // Composer has already deleted vendor/mikebronner/development-settings.
    symlink('vendor/mikebronner/development-settings/.ai', $project . '/.ai');

    $output = publishIn($project);

    expect(is_link($project . '/.ai'))->toBeFalse()
        ->and($output)->toContain('.ai (stale symlink into vendor)');

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

/*
 * The managed `.gitignore`. The package ships v2 and knows v1 and v2.
 */

const GITIGNORE_V1 = "/vendor\n";
const GITIGNORE_V2 = "/vendor\n/node_modules\n";

/**
 * A consumer whose `.gitignore` is a managed target, holding the given file.
 */
function gitignoreConsumer(string $local): string
{
    [$project] = makeConsumer([
        'app' => false,
        'manifest' => ['.gitignore' => [md5(GITIGNORE_V1), md5(GITIGNORE_V2)]],
        'sources' => ['resources/project/gitignore' => GITIGNORE_V2],
        'paths' => ['files' => ['resources/project/gitignore' => '.gitignore'], 'managed' => ['.gitignore']],
    ]);

    file_put_contents($project . '/.gitignore', $local);

    return $project;
}

/**
 * Run the prompt with the given key presses on a terminal that reads nothing
 * from STDIN, then put a real terminal back.
 */
function withKeyPresses(array $keys, Closure $callback): mixed
{
    $terminal = new class($keys) extends Terminal
    {
        /** @param list<string> $keys */
        public function __construct(private array $keys)
        {
            parent::__construct();
        }

        public function read(): string
        {
            return array_shift($this->keys) ?? throw new RuntimeException('The prompt asked for more keys than the test pressed.');
        }

        public function setTty(string $mode): void {}

        public function restoreTty(): void {}

        public function exit(): void {}

        public function cols(): int
        {
            return 80;
        }

        public function lines(): int
        {
            return 24;
        }

        public function initDimensions(): void {}
    };

    $property = new ReflectionProperty(Prompt::class, 'terminal');
    $property->setValue(null, $terminal);
    Prompt::setOutput(new BufferedConsoleOutput);

    try {
        return $callback();
    } finally {
        $property->setValue(null, new Terminal);
        Prompt::setOutput(new ConsoleOutput);
    }
}

it('updates the managed section of a marked .gitignore and keeps every project line below it', function (): void {
    $project = gitignoreConsumer(GITIGNORE_V1 . ManagedSection::MARKER . "\n!AGENTS.md\n/deprecations.log\n");

    $output = publishIn($project);

    expect(file_get_contents($project . '/.gitignore'))
        ->toBe(GITIGNORE_V2 . ManagedSection::MARKER . "\n!AGENTS.md\n/deprecations.log\n")
        ->and($output)->toContain('.gitignore')
        ->and($output)->toContain('1 updated');

    removeTempDir($project);
});

it('leaves a marked .gitignore alone when only its project lines differ', function (): void {
    $local = GITIGNORE_V2 . ManagedSection::MARKER . "\nphpunit.xml\n";
    $project = gitignoreConsumer($local);

    $output = publishIn($project);

    expect(file_get_contents($project . '/.gitignore'))->toBe($local)
        ->and($output)->toContain('1 unchanged')
        ->and($output)->not->toContain('locally modified');

    removeTempDir($project);
});

it('converts an unmarked .gitignore that is a shipped version, without asking', function (): void {
    $project = gitignoreConsumer(GITIGNORE_V1);

    publishIn($project);

    expect(file_get_contents($project . '/.gitignore'))->toBe(GITIGNORE_V2 . ManagedSection::MARKER . "\n");

    removeTempDir($project);
});

it('only warns about an edited, unmarked .gitignore in a non-interactive run', function (): void {
    $local = GITIGNORE_V1 . "!AGENTS.md\n";
    $project = gitignoreConsumer($local);

    $output = publishIn($project);

    expect(file_get_contents($project . '/.gitignore'))->toBe($local)
        ->and($output)->toContain('.gitignore (locally modified, no sync marker)')
        ->and($output)->toContain('1 locally-modified file(s) have no sync marker and were not updated')
        ->and($output)->toContain('1 skipped');

    removeTempDir($project);
});

it('converts an edited, unmarked .gitignore on consent, keeping the whole file below the marker', function (): void {
    $local = GITIGNORE_V1 . "!AGENTS.md\n";
    $project = gitignoreConsumer($local);

    withKeyPresses([Key::SPACE, Key::ENTER], fn () => publishIn($project, interactive: true));

    expect(file_get_contents($project . '/.gitignore'))->toBe(GITIGNORE_V2 . ManagedSection::MARKER . "\n" . $local);

    removeTempDir($project);
});

it('keeps an edited, unmarked .gitignore when the conversion is declined, which is the default', function (): void {
    $local = GITIGNORE_V1 . "!AGENTS.md\n";
    $project = gitignoreConsumer($local);

    $output = withKeyPresses([Key::ENTER], fn () => publishIn($project, interactive: true));

    expect(file_get_contents($project . '/.gitignore'))->toBe($local)
        ->and($output)->toContain('1 skipped');

    removeTempDir($project);
});

it('overwrites only the part above the marker when the user agrees to overwrite a locally modified .gitignore', function (): void {
    $projectLines = "!AGENTS.md\n/deprecations.log\n# no trailing newline, on purpose";
    $project = gitignoreConsumer(GITIGNORE_V1 . "/edited-above\n" . ManagedSection::MARKER . "\n" . $projectLines);

    $output = withKeyPresses([Key::SPACE, Key::ENTER], fn () => publishIn($project, interactive: true));

    expect(ManagedSection::split((string) file_get_contents($project . '/.gitignore')))
        ->toBe(['managed' => GITIGNORE_V2, 'project' => $projectLines])
        ->and($output)->toContain('.gitignore (locally modified)')
        ->and($output)->toContain('1 updated');

    removeTempDir($project);
});

it('keeps a locally modified .gitignore as it is when the overwrite is declined', function (): void {
    $local = GITIGNORE_V1 . "/edited-above\n" . ManagedSection::MARKER . "\n!AGENTS.md\n";
    $project = gitignoreConsumer($local);

    $output = withKeyPresses([Key::ENTER], fn () => publishIn($project, interactive: true));

    expect(file_get_contents($project . '/.gitignore'))->toBe($local)
        ->and($output)->toContain('1 skipped');

    removeTempDir($project);
});

it('reports a known-version .gitignore it cannot write as failed, never as updated', function (): void {
    $project = gitignoreConsumer(GITIGNORE_V1);
    chmod($project . '/.gitignore', 0444);

    $output = publishIn($project);

    chmod($project . '/.gitignore', 0644);

    expect(file_get_contents($project . '/.gitignore'))->toBe(GITIGNORE_V1)
        ->and($output)->toContain('.gitignore (write failed)')
        ->and($output)->toContain('.gitignore was not updated. Could not write ')
        ->and($output)->toContain('Permission denied')
        ->and($output)->toContain('0 updated')
        ->and($output)->toContain('1 skipped')
        ->and($output)->not->toContain('↻');

    removeTempDir($project);
});

it('reports a new file it cannot create as failed, never as created, and carries on', function (): void {
    [$project] = makeConsumer([
        'app' => false,
        'sources' => ['a.yml' => "a\n", 'b.yml' => "b\n"],
        'paths' => ['files' => ['a.yml' => 'locked/a.yml', 'b.yml']],
    ]);
    mkdir($project . '/locked', 0555);

    $output = publishIn($project);

    chmod($project . '/locked', 0755);

    expect(file_exists($project . '/locked/a.yml'))->toBeFalse()
        ->and(file_get_contents($project . '/b.yml'))->toBe("b\n")
        ->and($output)->toContain('locked/a.yml (write failed)')
        ->and($output)->toContain('locked/a.yml was not updated. Could not copy')
        ->and($output)->toContain('1 new')
        ->and($output)->toContain('1 skipped');

    removeTempDir($project);
});

it('counts an agreed overwrite it cannot write as skipped, and says why', function (): void {
    $local = GITIGNORE_V1 . "/edited-above\n" . ManagedSection::MARKER . "\n!AGENTS.md\n";
    $project = gitignoreConsumer($local);
    chmod($project . '/.gitignore', 0444);

    $output = withKeyPresses([Key::SPACE, Key::ENTER], fn () => publishIn($project, interactive: true));

    chmod($project . '/.gitignore', 0644);

    expect(file_get_contents($project . '/.gitignore'))->toBe($local)
        ->and($output)->toContain('.gitignore was not updated. Could not write')
        ->and($output)->toContain('0 updated')
        ->and($output)->toContain('1 skipped');

    removeTempDir($project);
});

it('does not touch a .gitignore holding the sync marker twice, and says why', function (): void {
    $local = GITIGNORE_V1 . ManagedSection::MARKER . "\n!AGENTS.md\n" . ManagedSection::MARKER . "\n";
    $project = gitignoreConsumer($local);

    $output = withKeyPresses([], fn () => publishIn($project, interactive: true));

    expect(file_get_contents($project . '/.gitignore'))->toBe($local)
        ->and($output)->toContain('.gitignore (sync marker appears twice, not touched)')
        ->and($output)->toContain('.gitignore holds the sync marker more than once, so it was not touched')
        ->and($output)->toContain('1 skipped');

    removeTempDir($project);
});

it('captures before an update and publishes after install and update', function (): void {
    expect(ComposerPlugin::getSubscribedEvents())->toBe([
        'pre-update-cmd' => 'captureBeforeUpdate',
        'post-install-cmd' => 'publish',
        'post-update-cmd' => 'publish',
    ]);
});
