<?php

declare(strict_types=1);

use Composer\Composer;
use Composer\Config;
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\BufferIO;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\RootPackage;
use Composer\PartialComposer;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Laravel\Prompts\Prompt;
use MikeBronner\DevelopmentSettings\ComposerPlugin;
use MikeBronner\DevelopmentSettings\Support\ContributionDetector;
use MikeBronner\DevelopmentSettings\Support\InstalledPackage;
use MikeBronner\DevelopmentSettings\Support\PackageConfig;
use MikeBronner\DevelopmentSettings\Support\ProjectKind;
use MikeBronner\DevelopmentSettings\Tests\Fixtures\AttachedTerminal;
use MikeBronner\DevelopmentSettings\Tests\Fixtures\DetachedTerminal;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

const COMPOSES = 'composes';

const COMPOSES_NOTHING = 'composes nothing';

const EXITS_WITH_ERROR = 'exits with an error';

const SILENT = 'silent';

const NON_INTERACTIVE = 'non-interactive, no terminal';

const NON_INTERACTIVE_ON_A_TERMINAL = 'non-interactive, on a terminal';

const INTERACTIVE = 'interactive, no terminal';

const INTERACTIVE_ON_A_TERMINAL = 'interactive, on a terminal';

/**
 * The Boost command stand-in, as PHP code. It records having run, the rooting
 * it saw, the arguments it was given and the stdout it was handed, then does
 * what Boost does in the named case: write a composed block into `AGENTS.md`,
 * write nothing and exit 0 (no agent found), or exit non-zero. A silent
 * stand-in prints nothing: an attached run writes to the suite's own output.
 */
function boostScript(string $behaviour, string $voice = ''): string
{
    $record = <<<PHP
        file_put_contents('boost.ran', json_encode([
            'APP_BASE_PATH' => \$_ENV['APP_BASE_PATH'] ?? null,
            'APP_ENV' => \$_ENV['APP_ENV'] ?? null,
            'TESTBENCH_WORKING_PATH' => getenv('TESTBENCH_WORKING_PATH'),
            'directories' => is_dir('bootstrap/cache') && is_dir('storage/framework/views'),
            'arguments' => array_slice(\$argv, 1),
        ]));
        file_put_contents('boost.stdout', fstat(STDOUT)['dev'] . ':' . fstat(STDOUT)['ino']);
        PHP;

    return $record . match ($behaviour) {
        COMPOSES => boostComposes($voice),
        COMPOSES_NOTHING => boostSays($voice, " echo 'Boost stand-in found no agent', PHP_EOL;"),
        EXITS_WITH_ERROR => boostFails($voice),
    };
}

function boostComposes(string $voice): string
{
    $block = var_export(agentBlock(), true);
    $saying = boostSays($voice, " echo 'Boost stand-in composed AGENTS.md', PHP_EOL;");

    return " file_put_contents('AGENTS.md', {$block});{$saying}";
}

function boostFails(string $voice): string
{
    $error = <<<PHP
        fwrite(STDERR, 'Boost stand-in: <error>command "boost:install"</error> is not defined');
        PHP;
    $saying = boostSays($voice, " {$error}");

    return "{$saying} exit(1);";
}

/**
 * The statement, unless the stand-in is silent.
 */
function boostSays(string $voice, string $statement): string
{
    return match ($voice) {
        SILENT => '',
        default => $statement,
    };
}

/**
 * The Testbench stand-in, as PHP code. Run as `package:discover`, it exits
 * with the given code: on 0 it records its arguments, and whether Boost had
 * run yet, as JSON in the repository's `bootstrap/cache/packages.php`, and on
 * any other it prints an error, as discovery does on a provider it cannot
 * load. Run as anything else, it is the Boost stand-in.
 */
function testbenchScript(string $behaviour, string $voice, int $discoveryExit): string
{
    $discover = match ($discoveryExit) {
        0 => <<<PHP
            \$run = ['arguments' => array_slice(\$argv, 1), 'boost ran' => is_file('boost.ran')];
            file_put_contents('bootstrap/cache/packages.php', json_encode(\$run));
            PHP,
        default => <<<PHP
            echo 'Discovery stand-in: <error>Class "Provider" not found</error>';
            PHP,
    };
    $exit = "exit({$discoveryExit});";

    return "if ((\$argv[1] ?? null) === 'package:discover') { {$discover} {$exit} }\n"
        . boostScript($behaviour, $voice);
}

/**
 * What the Testbench stand-in recorded when it rebuilt the discovery cache.
 *
 * @return array<string, mixed>
 */
function discoveryRun(string $project): array
{
    return json_decode(
            (string) file_get_contents("{$project}/bootstrap/cache/packages.php"),
            associative: true,
        );
}

/**
 * The Boost command, run straight through PHP. The trailing `--` hands an
 * appended flag to the script, not to PHP.
 */
function boostStandIn(string $behaviour, string $voice = ''): string
{
    $script = escapeshellarg(boostScript($behaviour, $voice));

    return escapeshellarg(PHP_BINARY) . " -r {$script} --";
}

/**
 * A consuming project with the package installed in vendor, answered as the
 * project and the package directory. Every option is optional:
 *
 * - `command`, `interactiveCommand`: replace the two Boost commands.
 * - `boost`: what the Boost stand-in does; `voice`: SILENT to print nothing.
 * - `app`: false for a project with no artisan of its own.
 * - `testbench`: true to install a Testbench that runs the Boost stand-in.
 * - `discoveryExit`: the exit code of the Testbench stand-in's `package:discover`.
 * - `paths`: merged into the tracked paths of the package.
 * - `manifest`, `captured`, `packageManifest`: the three shipped manifests.
 * - `sources`: files written into the package, path => contents.
 *
 * @param  array<string, mixed>  $options
 * @return array{string, string}
 */
function makeConsumer(array $options = []): array
{
    $project = makeTempDir('devset-publish-');
    $package = "{$project}/vendor/" . InstalledPackage::NAME;
    $behaviour = data_get($options, 'boost', COMPOSES);
    $voice = data_get($options, 'voice', '');

    seedFiles($project, [
        'composer.json' => json_encode(['require-dev' => new stdClass()]) . "\n",
        ...match (data_get($options, 'app', true)) {
            true => ['artisan' => "#!/usr/bin/env php\n"],
            false => [],
        },
        ...match (data_get($options, 'testbench', false)) {
            true => [ProjectKind::TESTBENCH => "<?php\n" . testbenchScript(
                $behaviour,
                $voice,
                data_get($options, 'discoveryExit', 0),
            )],
            false => [],
        },
    ]);
    seedFiles($package, [
        ...consumerManifests($options),
        ...packageSources(),
        PackageConfig::FILE => '<?php return ' . var_export(consumerConfig($options), true) . ';',
        ...data_get($options, 'sources', []),
    ]);

    return [$project, $package];
}

/**
 * The config of the installed package, with a Boost stand-in for its
 * commands.
 *
 * @param  array<string, mixed>  $options
 * @return array<string, mixed>
 */
function consumerConfig(array $options): array
{
    $boost = boostStandIn(data_get($options, 'boost', COMPOSES), data_get($options, 'voice', ''));
    $shipped = new PackageConfig(require REPOSITORY_ROOT . '/' . PackageConfig::FILE);

    return [
        'hooks' => [
            'command' => data_get($options, 'command', $boost),
            'interactive_command' => data_get($options, 'interactiveCommand', "{$boost} attached"),
            'discover_command' => escapeshellarg(PHP_BINARY) . ' artisan package:discover',
            'description' => 'Composing Laravel Boost guidelines and skills...',
        ],
        'package' => $shipped->entries(PackageConfig::PACKAGE),
        'capture' => ['resources/boost'],
        'paths' => [
            'legacy_symlinks' => ['.ai'],
            'ignore' => ['.DS_Store'],
            ...data_get($options, 'paths', []),
        ],
    ];
}

/**
 * @param  array<string, mixed>  $options
 * @return array<string, string>
 */
function consumerManifests(array $options): array
{
    return [
        'manifest.json' => json_encode(data_get($options, 'manifest', new stdClass())),
        ContributionDetector::MANIFEST_FILE => json_encode(
                data_get($options, 'captured', new stdClass()),
            ),
        ProjectKind::MANIFEST_FILE => json_encode(
                data_get($options, 'packageManifest', shippedPackageManifest()),
            ),
    ];
}

/**
 * The artisan shim and .gitattributes sources, as this repository ships them.
 *
 * @return array<string, string>
 */
function packageSources(): array
{
    $shipped = new PackageConfig(require REPOSITORY_ROOT . '/' . PackageConfig::FILE);

    return collect(data_get($shipped->entries(PackageConfig::PACKAGE), 'files'))
        ->keys()
        ->mapWithKeys(fn (string $source): array => [
            $source => (string) file_get_contents(REPOSITORY_ROOT . "/{$source}"),
        ])
        ->all();
}

/**
 * Run a Composer hook of the plugin in the project, the way the given run of
 * Composer would, and answer what it printed.
 */
function publishIn(string $project, string $run = NON_INTERACTIVE, string $hook = 'publish'): string
{
    $inputOutput = new BufferIO();
    $isInteractive = in_array($run, [INTERACTIVE, INTERACTIVE_ON_A_TERMINAL], strict: true);
    $terminalRuns = [INTERACTIVE_ON_A_TERMINAL, NON_INTERACTIVE_ON_A_TERMINAL];
    $onTerminal = in_array($run, $terminalRuns, strict: true);
    $terminal = match ($onTerminal) {
        true => new AttachedTerminal(),
        false => new DetachedTerminal(),
    };
    $workingDirectory = (string) getcwd();

    $isInteractive && $inputOutput->setUserInputs([]);
    chdir($project);

    try {
        (new ComposerPlugin($terminal))
            ->{$hook}(new Event(ScriptEvents::POST_UPDATE_CMD, composerIn($project), $inputOutput));
    } finally {
        chdir($workingDirectory);
    }

    return $inputOutput->getOutput();
}

/**
 * Dispatch the event in the project through Composer's own event dispatcher,
 * with the plugin subscribed as Composer subscribes it, so the project's own
 * scripts for the event run after the plugin's hook. Answers what was printed;
 * a failure the plugin lets escape is thrown.
 */
function dispatchIn(
    string $project,
    string $eventName = ScriptEvents::POST_UPDATE_CMD,
    int $verbosity = OutputInterface::VERBOSITY_NORMAL,
): string {
    $inputOutput = new BufferIO(verbosity: $verbosity);
    $composer = composerIn($project);
    $dispatching = new PartialComposer();
    $dispatching->setPackage($composer->getPackage());
    $dispatching->setConfig(new Config(useEnvironment: false, baseDir: $project));
    $dispatcher = new EventDispatcher($dispatching, $inputOutput);
    $dispatcher->addSubscriber(new ComposerPlugin(new DetachedTerminal()));
    $workingDirectory = (string) getcwd();

    chdir($project);

    try {
        $event = new Event($eventName, $composer, $inputOutput);
        $dispatcher->dispatch(null, $event);
    } finally {
        chdir($workingDirectory);
    }

    return $inputOutput->getOutput();
}

/**
 * A consuming project whose installed package fails while it publishes: its
 * config runs the given PHP statement. The project's own `post-install-cmd`
 * and `post-update-cmd` scripts write `later.ran` when Composer gets to them.
 *
 * @return array{string, string}
 */
function brokenConsumer(string $statement): array
{
    $sources = [PackageConfig::FILE => "<?php {$statement}\n"];
    [$project, $package] = makeConsumer(['sources' => $sources]);
    $laterScript = ['touch later.ran'];
    $composerJson = [
        'scripts' => [
            ScriptEvents::POST_INSTALL_CMD => $laterScript,
            ScriptEvents::POST_UPDATE_CMD => $laterScript,
        ],
    ];
    file_put_contents("{$project}/composer.json", json_encode($composerJson) . "\n");

    return [$project, $package];
}

/**
 * Run the callback with the `CI` environment variable set to the value, or
 * unset for null, and restore it afterwards.
 */
function withCi(?string $value, Closure $callback): mixed
{
    $before = getenv('CI');
    putenv(match ($value) {
        null => 'CI',
        default => "CI={$value}",
    });

    try {
        return $callback();
    } finally {
        putenv(match ($before) {
            false => 'CI',
            default => "CI={$before}",
        });
    }
}

/**
 * Composer as it runs in the project: its root package loaded from the
 * project's composer.json, or an empty one when there is none.
 */
function composerIn(string $project): Composer
{
    $composerFile = "{$project}/composer.json";
    $config = match (file_exists($composerFile)) {
        true => json_decode((string) file_get_contents($composerFile), associative: true),
        false => [],
    };
    $composer = new Composer();
    $composer->setPackage((new ArrayLoader())->load(
            ['name' => '__root__', 'version' => '1.0.0', ...$config],
            RootPackage::class,
        ));

    return $composer;
}

/**
 * Write the project's composer.json with the given requirements.
 *
 * @param  array<string, array<string, string>>  $requirements  key => package => constraint
 */
function requireInProject(string $project, array $requirements): void
{
    file_put_contents("{$project}/composer.json", json_encode($requirements) . "\n");
}

/**
 * The package manifest as this repository ships it: every current source known.
 *
 * @return array<string, list<string>>
 */
function shippedPackageManifest(): array
{
    return json_decode(
            (string) file_get_contents(REPOSITORY_ROOT . '/' . ProjectKind::MANIFEST_FILE),
            associative: true,
        );
}

function shimSource(): string
{
    return (string) file_get_contents(REPOSITORY_ROOT . '/resources/project/artisan');
}

function boostRan(string $project): bool
{
    return file_exists("{$project}/boost.ran");
}

/**
 * What the Boost stand-in recorded about its run.
 *
 * @return array<string, mixed>
 */
function boostRun(string $project): array
{
    return json_decode((string) file_get_contents("{$project}/boost.ran"), associative: true);
}

/**
 * Whether Boost wrote to this process's own stdout, which is what an attached
 * run hands it. A captured run writes to a pipe instead.
 */
function boostSharedOurStdout(string $project): bool
{
    $stdout = fstat(STDOUT);
    $identity = data_get($stdout, 'dev') . ':' . data_get($stdout, 'ino');

    return file_get_contents("{$project}/boost.stdout") === $identity;
}

/**
 * @return array<string, mixed>
 */
function boostConfigIn(string $project): array
{
    return json_decode((string) file_get_contents("{$project}/boost.json"), associative: true);
}

/**
 * A package repository whose `.gitignore` is a managed target holding the
 * given file. The package ships v2 and knows v1 and v2.
 */
function gitignoreConsumer(string $local): string
{
    [$project] = makeConsumer([
        'app' => false,
        'manifest' => ['.gitignore' => [md5(GITIGNORE_V1), md5(GITIGNORE_V2)]],
        'sources' => ['resources/project/gitignore' => GITIGNORE_V2],
        'paths' => [
            'files' => ['resources/project/gitignore' => '.gitignore'],
            'managed' => ['.gitignore'],
        ],
    ]);

    file_put_contents("{$project}/.gitignore", $local);

    return $project;
}

/**
 * Run the prompt with the given key presses on a fake terminal, then put the
 * real console output back.
 *
 * @param  list<string>  $keys
 */
function withKeyPresses(array $keys, Closure $callback): mixed
{
    Prompt::fake($keys);

    try {
        return $callback();
    } finally {
        Prompt::setOutput(new ConsoleOutput());
    }
}
