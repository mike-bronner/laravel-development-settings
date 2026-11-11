<?php

declare(strict_types=1);

use Composer\Composer;
use Composer\IO\BufferIO;
use Composer\Script\ScriptEvents;
use MikeBronner\DevelopmentSettings\ComposerPlugin;
use MikeBronner\DevelopmentSettings\Plugin\Publisher;
use MikeBronner\DevelopmentSettings\Support\InstalledPackage;
use MikeBronner\DevelopmentSettings\Support\PackageConfig;
use MikeBronner\DevelopmentSettings\Support\SystemProcess;
use Symfony\Component\Console\Output\OutputInterface;

const UNDEFINED_METHOD = 'return (new stdClass)->passthru();';

const RE_RUN = "Run \"composer update\" again to finish setup.";

const RE_RUN_INSTALL = "Run \"composer install\" again to finish setup.";

const UPGRADE_RUN = <<<PHP
    require %s;
    Composer\\Util\\ErrorHandler::register();
    foreach (glob(%s) as \$helper) {
        require_once \$helper;
    }
    \$configFile = %s;
    \$earlier = require \$configFile;
    file_put_contents(\$configFile, %s);
    \$isStale = (require \$configFile) === \$earlier;
    echo json_encode(['stale' => \$isStale, 'output' => publishIn(%s)]);
    PHP;

const SHIPPED_TARGETS_ARGUMENT = "                        \$plan->shippedTargets(),\n";

afterEach(function (): void {
    removeTempDir($this->project);
});

it('captures before an update and publishes after install and update', function (): void {
    $this->project = makeTempDir();

    expect(ComposerPlugin::getSubscribedEvents())->toBe([
        'pre-update-cmd' => 'captureBeforeUpdate',
        'post-install-cmd' => 'publish',
        'post-update-cmd' => 'publish',
    ]);
});

it('does nothing when Composer activates, deactivates or uninstalls it', function (): void {
    $this->project = makeTempDir();
    $plugin = new ComposerPlugin;
    $output = new BufferIO;

    $plugin->activate(new Composer, $output);
    $plugin->deactivate(new Composer, $output);
    $plugin->uninstall(new Composer, $output);

    expect($output->getOutput())->toBe('');
});

it('publishes the package it finds in the project\'s vendor directory', function (): void {
    [$this->project] = makeConsumer();

    expect(publishIn($this->project))
        ->toContain('Developer Settings', '... done')
        ->not
        ->toContain('could not finish');
});

it('says so when the package is missing from vendor', function (): void {
    $this->project = makeTempDir();

    expect(publishIn($this->project))
        ->toContain('Could not locate the ' . InstalledPackage::NAME . ' package directory');
});

it('publishes nothing into its own repository', function (): void {
    $this->project = makeTempDir();
    $composerJson = json_encode(['name' => InstalledPackage::NAME]);
    file_put_contents("{$this->project}/composer.json", $composerJson);
    file_put_contents("{$this->project}/CLAUDE.md", "Maintainer notes.\n");

    expect(publishIn($this->project))->toBe('');
    expect(file_get_contents("{$this->project}/CLAUDE.md"))->toBe("Maintainer notes.\n");
});

it('names edited sources before an update, and nothing without the package', function (): void {
    [$this->project, $package] = makeConsumer([
        'sources' => ['resources/boost/guidelines/01-identity.md' => "Edited\n"],
    ]);

    expect(publishIn($this->project, hook: 'captureBeforeUpdate'))->toContain('01-identity.md');

    removeTempDir($package);

    expect(publishIn($this->project, hook: 'captureBeforeUpdate'))->toBe('');
});

it('publishes from the config on disk when the run compiled an older copy', function (
    string $restriction,
): void {
    [$this->project, $package] = makeConsumer(['app' => false, 'sources' => ['new.md' => "New\n"]]);
    $current = '<?php return '
        . var_export(consumerConfig(['paths' => ['files' => ['new.md']]]), true) . ';';
    $script = sprintf(
            UPGRADE_RUN,
            var_export(REPOSITORY_ROOT . '/vendor/autoload.php', true),
            var_export(REPOSITORY_ROOT . '/tests/Helpers/*.php', true),
            var_export("{$package}/" . PackageConfig::FILE, true),
            var_export($current, true),
            var_export($this->project, true),
        );

    $result = (new SystemProcess)->capture(cachingPhpCommand($script, $restriction));
    $run = json_decode($result->output(), associative: true);

    expect($run)->toBeArray($result->output())
        ->and(data_get($run, 'stale'))
        ->toBeTrue($result->output())
        ->and(data_get($run, 'output'))
        ->toContain('new.md')
        ->and(data_get($run, 'output'))
        ->not
        ->toContain('could not finish')
        ->and(file_get_contents("{$this->project}/new.md"))
        ->toBe("New\n");
})->with(OPCACHE_API)
    ->skip(! function_exists('opcache_invalidate'), NO_OPCACHE);

it('publishes with the new classes when a file cache holds older ones', function (): void {
    [$this->project, $package] = makeConsumer([
        'app' => false,
        'paths' => ['files' => ['new.md']],
        'sources' => ['new.md' => "New\n"],
    ]);
    $publisherFile = "{$package}/src/Plugin/Publisher.php";
    copyTree(REPOSITORY_ROOT . '/src', "{$package}/src");
    $current = (string) file_get_contents($publisherFile);
    $older = str_replace(SHIPPED_TARGETS_ARGUMENT, '', $current);
    $olderEndLine = substr_count(rtrim($older), "\n") + 1;
    $compile = sprintf(
            "['compiled' => opcache_compile_file(%s)]",
            var_export($publisherFile, true),
        );
    $endLine = sprintf(
            "['endLine' => (new ReflectionClass(%s))->getEndLine()]",
            var_export(Publisher::class, true),
        );
    $publish = sprintf("['output' => publishIn(%s)]", var_export($this->project, true));

    file_put_contents($publisherFile, $older);
    $compiled = runWithFileCache($package, $compile);
    file_put_contents($publisherFile, $current);
    $loaded = runWithFileCache($package, $endLine);
    $output = (string) data_get(runWithFileCache($package, $publish), 'output');

    expect($older)
        ->not
        ->toBe($current)
        ->and($compiled)
        ->toBe(['compiled' => true])
        ->and($loaded)
        ->toBe(['endLine' => $olderEndLine])
        ->and($output)
        ->toContain('new.md')
        ->not
        ->toContain('could not finish')
        ->and(file_get_contents("{$this->project}/new.md"))
        ->toBe("New\n");
})->skip(! function_exists('opcache_compile_file'), NO_OPCACHE);

it('contains a publish failure outside CI, and the later scripts run', function (): void {
    [$this->project, $package] = brokenConsumer(UNDEFINED_METHOD);
    $configFile = realpath("{$package}/" . PackageConfig::FILE);

    $output = withCi(null, fn (): string => dispatchIn($this->project));

    expect($output)
        ->toContain(
            'Developer Settings could not finish setting up this project.',
            "Error: Call to undefined method stdClass::passthru() in {$configFile}:1",
            RE_RUN,
        )
        ->not
        ->toContain(RE_RUN_INSTALL)
        ->and("{$this->project}/later.ran")
        ->toBeFile();
});

it('fails the run under any set CI, and runs no later script', function (string $ciValue): void {
    [$this->project] = brokenConsumer(UNDEFINED_METHOD);
    $dispatchUnderCi = fn (): string => dispatchIn($this->project);
    $dispatch = fn (): string => withCi($ciValue, $dispatchUnderCi);

    expect($dispatch)
        ->toThrow(Error::class, 'Call to undefined method stdClass::passthru()')
        ->and("{$this->project}/later.ran")
        ->not
        ->toBeFile();
})->with(['true', 'false']);

it('names composer install after an install, and never composer update', function (): void {
    [$this->project] = brokenConsumer(UNDEFINED_METHOD);
    $dispatch = fn (): string => dispatchIn($this->project, ScriptEvents::POST_INSTALL_CMD);

    expect(withCi(null, $dispatch))
        ->toContain(RE_RUN_INSTALL)
        ->not
        ->toContain(RE_RUN)
        ->and("{$this->project}/later.ran")
        ->toBeFile();
});

it('reads an empty CI as unset', function (): void {
    [$this->project] = brokenConsumer(UNDEFINED_METHOD);

    expect(withCi('', fn (): string => dispatchIn($this->project)))
        ->toContain(RE_RUN);
});

it('prints a tag in the cause as text, not as a style', function (): void {
    $statement = <<<PHP
        throw new RuntimeException('<comment>tagged</comment>');
        PHP;
    $cause = <<<TEXT
        RuntimeException: <comment>tagged</comment> in
        TEXT;
    [$this->project] = brokenConsumer($statement);

    expect(withCi(null, fn (): string => dispatchIn($this->project)))
        ->toContain($cause);
});

it('prints the trace of a contained failure at -v only', function (): void {
    [$this->project] = brokenConsumer(UNDEFINED_METHOD);
    $trace = 'ComposerPlugin->publish(';
    $verbose = OutputInterface::VERBOSITY_VERBOSE;

    expect(withCi(null, fn (): string => dispatchIn($this->project)))
        ->not
        ->toContain($trace)
        ->and(withCi(null, fn (): string => dispatchIn($this->project, verbosity: $verbose)))
        ->toContain($trace);
});

it('contains nothing when the publish reports its own failure', function (): void {
    [$this->project] = makeConsumer(['boost' => EXITS_WITH_ERROR]);

    expect(withCi('true', fn (): string => dispatchIn($this->project)))
        ->toContain('Developer Settings', '... failed')
        ->not
        ->toContain('could not finish');
});
