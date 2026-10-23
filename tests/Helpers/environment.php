<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\ProcessResult;
use MikeBronner\DevelopmentSettings\Support\SystemProcess;

const TIA_DETECT_STEP = 'Detect the test environment';

const TIA_SERVICES_STEP = 'Start the database and Redis';

const TIA_SQLITE_STEP = <<<TEXT
    Create the SQLite database
    TEXT;

const TIA_WRITABLE_STEP = 'Make storage and bootstrap/cache writable';

const BROWSER_PLUGIN = 'pestphp/pest-plugin-browser';

const MODE_WORLD_WRITABLE = 0777;

const DOCKER_CALL_END = '<end of call>';

const HEALTH_POLLS = 60;

const PLAYWRIGHT_WARNING = '::warning title=Pest TIA baseline::composer.json requires'
    . ' pestphp/pest-plugin-browser, but no package-lock.json locks playwright, so the workflow'
    . ' installs no browser. Run npm install --save-dev playwright and commit package-lock.json.';

const TIA_SERVICE_INPUTS = [
    'TIA_DATABASE',
    'TIA_DATABASE_IMAGE',
    'TIA_DATABASE_PORT',
    'TIA_DATABASE_NAME',
    'TIA_DATABASE_USERNAME',
    'TIA_DATABASE_PASSWORD',
    'TIA_REDIS',
    'TIA_REDIS_IMAGE',
    'TIA_REDIS_PORT',
    'TIA_REDIS_PASSWORD',
];

const NO_ENVIRONMENT = [
    'database' => '',
    'database-image' => '',
    'database-port' => '',
    'database-name' => '',
    'database-username' => '',
    'database-password' => '',
    'sqlite-file' => '',
    'redis' => '',
    'redis-image' => '',
    'redis-port' => '',
    'redis-password' => '',
    'env-file' => '',
    'app-key' => '',
    'npm' => '',
    'build' => '',
    'browsers' => '',
    'writable' => '',
    'php-extensions' => '',
];

const CROSSBIBLE_PHPUNIT = <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <phpunit bootstrap="vendor/autoload.php">
        <php>
            <env name="APP_KEY" value="base64:2fl3IfNvXJnTn/lMeFu4qHUh9FT3l4DdZ3LZML/iNqc="/>
            <env name="CACHE_DRIVER" value="array"/>
            <env name="DB_CONNECTION" value="pgsql"/>
            <env name="DB_DATABASE" value="testing"/>
            <env name="DB_PASSWORD" value=""/>
            <env name="DB_USERNAME" value="root"/>
            <env name="SESSION_DRIVER" value="array"/>
            <!-- <env name="DB_CONNECTION" value="sqlite"/> -->
            <server name="DB_CONNECTION" value="pgsql-testing"/>
        </php>
    </phpunit>
    XML;

const COMMENTED_PHPUNIT = <<<XML
    <phpunit><php><!-- <env name="DB_CONNECTION" value="pgsql"/> --></php></phpunit>
    XML;

const BROKEN_PHPUNIT = <<<XML
    <phpunit><php>
    XML;

const CROSSBIBLE_ENV_EXAMPLE = <<<ENV
    DB_CONNECTION=pgsql
    DB_HOST=127.0.0.1
    DB_PORT=5432
    DB_DATABASE=crossbible
    DB_USERNAME=root
    DB_PASSWORD=
    APP_KEY=base64:gtcI8Q0W1i4gUnJPXgNUm0cpGuqfMZmLe/PUv9J8dUA=
    SESSION_DRIVER=redis
    REDIS_HOST=127.0.0.1
    REDIS_PASSWORD=null
    REDIS_PORT=6379

    ENV;

const QUOTED_ENV_EXAMPLE = <<<ENV
    DB_CONNECTION=pgsql
    DB_PORT=5433
    DB_DATABASE="app_tests"
    DB_USERNAME=sail # local
    DB_PASSWORD='secret'

    ENV;

const COMMENTED_ENV_EXAMPLE = <<<ENV
    # DB_CONNECTION=pgsql
    DB_CONNECTION=sqlite
    # DB_DATABASE=laravel

    ENV;

const SKELETON_ENV_EXAMPLE = <<<ENV
    APP_NAME=Laravel
    APP_ENV=local
    APP_KEY=
    APP_DEBUG=true
    APP_URL=http://localhost

    APP_LOCALE=en
    APP_FALLBACK_LOCALE=en
    APP_FAKER_LOCALE=en_US

    APP_MAINTENANCE_DRIVER=file
    # APP_MAINTENANCE_STORE=database

    PHP_CLI_SERVER_WORKERS=4

    BCRYPT_ROUNDS=12

    LOG_CHANNEL=stack
    LOG_STACK=single
    LOG_DEPRECATIONS_CHANNEL=null
    LOG_LEVEL=debug

    DB_CONNECTION=sqlite
    # DB_HOST=127.0.0.1
    # DB_PORT=3306
    # DB_DATABASE=laravel
    # DB_USERNAME=root
    # DB_PASSWORD=

    SESSION_DRIVER=database
    SESSION_LIFETIME=120
    SESSION_ENCRYPT=false
    SESSION_PATH=/
    SESSION_DOMAIN=null

    BROADCAST_CONNECTION=log
    FILESYSTEM_DISK=local
    QUEUE_CONNECTION=database

    CACHE_STORE=database
    # CACHE_PREFIX=

    MEMCACHED_HOST=127.0.0.1

    REDIS_CLIENT=phpredis
    REDIS_HOST=127.0.0.1
    REDIS_PASSWORD=null
    REDIS_PORT=6379

    MAIL_MAILER=log
    MAIL_SCHEME=null
    MAIL_HOST=127.0.0.1
    MAIL_PORT=2525
    MAIL_USERNAME=null
    MAIL_PASSWORD=null
    MAIL_FROM_ADDRESS="hello@example.com"
    MAIL_FROM_NAME="\${APP_NAME}"

    AWS_ACCESS_KEY_ID=
    AWS_SECRET_ACCESS_KEY=
    AWS_DEFAULT_REGION=us-east-1
    AWS_BUCKET=
    AWS_USE_PATH_STYLE_ENDPOINT=false

    VITE_APP_NAME="\${APP_NAME}"

    ENV;

const SKELETON_PHPUNIT = <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
             xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
             bootstrap="vendor/autoload.php"
             colors="true"
    >
        <testsuites>
            <testsuite name="Unit">
                <directory>tests/Unit</directory>
            </testsuite>
            <testsuite name="Feature">
                <directory>tests/Feature</directory>
            </testsuite>
        </testsuites>
        <source>
            <include>
                <directory>app</directory>
            </include>
        </source>
        <php>
            <env name="APP_ENV" value="testing"/>
            <env name="APP_MAINTENANCE_DRIVER" value="file"/>
            <env name="BCRYPT_ROUNDS" value="4"/>
            <env name="CACHE_STORE" value="array"/>
            <env name="DB_CONNECTION" value="sqlite"/>
            <env name="DB_DATABASE" value=":memory:"/>
            <env name="MAIL_MAILER" value="array"/>
            <env name="PULSE_ENABLED" value="false"/>
            <env name="QUEUE_CONNECTION" value="sync"/>
            <env name="SESSION_DRIVER" value="array"/>
            <env name="TELESCOPE_ENABLED" value="false"/>
        </php>
    </phpunit>
    XML;

const PLAYWRIGHT_LOCK = <<<JSON
    {"lockfileVersion": 3, "packages": {"": {}, "node_modules/playwright": {"version": "1.56.0"}}}
    JSON;

const PLAYWRIGHT_LOCK_V1 = <<<JSON
    {"lockfileVersion": 1, "dependencies": {"playwright": {"version": "1.40.0"}}}
    JSON;

const LOCK_WITHOUT_PLAYWRIGHT = <<<JSON
    {"lockfileVersion": 3, "packages": {"": {}, "node_modules/vite": {"version": "7.0.0"}}}
    JSON;

const CROSSBIBLE_ENV_BLOCK = [
    'DB_USERNAME' => 'postgres',
    'DB_PASSWORD' => 'postgres',
    'TEST_DB_DATABASE' => 'testing',
];

const PACKAGE_WITH_BUILD = <<<JSON
    {"private": true, "scripts": {"dev": "vite", "build": "vite build"}}
    JSON;

const PACKAGE_WITHOUT_BUILD = <<<JSON
    {"private": true, "scripts": {"dev": "vite"}}
    JSON;

const BROKEN_JSON = <<<JSON
    {"scripts":
    JSON;

function crossbibleShapedProject(): array
{
    return [
        'phpunit.xml' => CROSSBIBLE_PHPUNIT,
        '.env.example' => CROSSBIBLE_ENV_EXAMPLE,
        'composer.json' => composerJson(requireDev: [BROWSER_PLUGIN]),
        'package.json' => PACKAGE_WITH_BUILD,
        'package-lock.json' => PLAYWRIGHT_LOCK,
        'artisan' => '',
        'storage/app/.gitignore' => '',
        'bootstrap/cache/.gitignore' => '',
    ];
}

function composerJson(array $require = [], array $requireDev = []): string
{
    return (string) json_encode([
        'require' => (object) array_fill_keys($require, '*'),
        'require-dev' => (object) array_fill_keys($requireDev, '*'),
    ]);
}

function phpunitXml(array $env = [], array $server = []): string
{
    $elements = collect(['env' => $env, 'server' => $server])
        ->flatMap(fn (array $values, string $kind): array => phpunitElements($kind, $values))
        ->implode('');

    return <<<XML
        <phpunit><php>{$elements}</php></phpunit>
        XML;
}

function phpunitElements(string $kind, array $values): array
{
    return collect($values)
        ->map(fn (string $value, string $name): string => <<<XML
            <{$kind} name="{$name}" value="{$value}"/>
            XML)
        ->values()
        ->all();
}

function phpunitFile(array $env = [], array $server = []): array
{
    return ['phpunit.xml' => phpunitXml($env, $server)];
}

function connection(string $connection): array
{
    return phpunitFile(['DB_CONNECTION' => $connection]);
}

function envExample(array $values): string
{
    return collect($values)
        ->map(fn (string $value, string $name): string => "{$name}={$value}\n")
        ->implode('');
}

function exampleFile(array $values): array
{
    return ['.env.example' => envExample($values)];
}

function laravelApp(array $values): array
{
    return ['artisan' => '', ...exampleFile($values)];
}

function detectionRun(array $files = [], array $environment = []): ProcessResult
{
    test()->project = makeTempDir('devset-tia-environment-');
    seedFiles(test()->project, ['composer.json' => composerJson(), ...$files]);

    $result = runTiaEnvironmentStep(test()->project, $environment);

    expect($result->exitCode())->toBe(0, $result->output());

    return $result;
}

function detectedEnvironment(array $files = [], array $environment = []): array
{
    detectionRun($files, $environment);

    return detectedOutputs();
}

function detectedOutputs(): array
{
    return stepOutputs((string) file_get_contents(test()->project . '/github-output'));
}

function npmProject(string $lock, ?string $composer = null): array
{
    return [
        'composer.json' => $composer ?? composerJson(),
        'package.json' => PACKAGE_WITH_BUILD,
        'package-lock.json' => $lock,
    ];
}

function frontEndNotice(string $otherLock): string
{
    return '::notice title=Pest TIA baseline::package.json has no package-lock.json'
        . "{$otherLock}, so the workflow installs and builds no front end."
        . ' Install and build it in .github/actions/tia-baseline-after-install.';
}

function detectedSettings(array $keys, array $files = [], array $environment = []): array
{
    return collect(detectedEnvironment($files, $environment))
        ->only($keys)
        ->all();
}

function runTiaEnvironmentStep(string $project, array $environment = []): ProcessResult
{
    seedFiles($project, [
        'detect-environment.php' => tiaWorkflowStepScript(TIA_DETECT_STEP),
        'github-output' => '',
    ]);
    $output = "{$project}/github-output";
    $command = isolatedEnvironment([...$environment, 'GITHUB_OUTPUT' => $output]);
    $php = escapeshellarg(PHP_BINARY);

    return (new SystemProcess)->capture("{$command} {$php} detect-environment.php", $project);
}

function runTiaStep(string $step, string $project, array $environment): ProcessResult
{
    $command = isolatedEnvironment($environment);
    $script = escapeshellarg(tiaWorkflowStepScript($step));

    return (new SystemProcess)->capture("{$command} bash -e -o pipefail -c {$script}", $project);
}

function runTiaServicesStep(
    string $project,
    array $environment,
    array $health = ['healthy'],
): ProcessResult {
    seedFiles($project, [
        'bin/docker' => fakeDocker($project),
        'bin/sleep' => fakeSleep($project),
        'docker-health' => implode("\n", $health) . "\n",
        'docker-calls' => '',
        'sleeps' => '',
        'github-env' => '',
    ]);
    chmod("{$project}/bin/docker", TIA_EXECUTABLE);
    chmod("{$project}/bin/sleep", TIA_EXECUTABLE);

    return runTiaStep(TIA_SERVICES_STEP, $project, [
        ...array_fill_keys(TIA_SERVICE_INPUTS, ''),
        ...$environment,
        'PATH' => "{$project}/bin:" . getenv('PATH'),
        'GITHUB_ENV' => "{$project}/github-env",
        'RUNNER_TEMP' => "{$project}/runner-temp",
    ]);
}

function fakeDocker(string $project): string
{
    $calls = escapeshellarg("{$project}/docker-calls");
    $health = escapeshellarg("{$project}/docker-health");
    $end = escapeshellarg(DOCKER_CALL_END);

    return <<<BASH
        #!/usr/bin/env bash
        printf '%s\\n' "\$@" {$end} >> {$calls}
        case "\$1" in
            inspect)
                polls=\$(grep -cx inspect {$calls})
                sed -n "\${polls}p" {$health} | grep . || tail -n 1 {$health}
                ;;
            logs) echo 'the container log' ;;
        esac

        BASH;
}

function fakeSleep(string $project): string
{
    $sleeps = escapeshellarg("{$project}/sleeps");

    return <<<BASH
        #!/usr/bin/env bash
        printf '%s\\n' "\$*" >> {$sleeps}

        BASH;
}

function dockerCalls(string $project): array
{
    $recorded = (string) file_get_contents("{$project}/docker-calls");

    return collect(explode(DOCKER_CALL_END . "\n", $recorded))
        ->filter()
        ->map(fn (string $call): array => explode("\n", substr($call, 0, -1)))
        ->values()
        ->all();
}

function dockerArguments(string $project, int $call = 0): string
{
    return "\n" . implode("\n", (array) data_get(dockerCalls($project), $call)) . "\n";
}

function argumentRun(array $arguments): string
{
    return "\n" . implode("\n", $arguments) . "\n";
}

function sleeps(string $project): array
{
    return (array) file("{$project}/sleeps", FILE_IGNORE_NEW_LINES);
}

function databaseInitScript(string $project): string
{
    return (string) file_get_contents("{$project}/runner-temp/tia-database-init/user.sql");
}

function exportedEnvironment(string $project): string
{
    return (string) file_get_contents("{$project}/github-env");
}

function isolatedEnvironment(array $environment): string
{
    return collect(['PATH' => (string) getenv('PATH'), ...$environment])
        ->map(fn (string $value, string $name): string => "{$name}=" . escapeshellarg($value))
        ->prepend('env -i')
        ->implode(' ');
}

function databaseInputs(
    string $driver,
    int $port,
    string $username = 'app',
    string $password = 'secret',
): array {
    return [
        'TIA_DATABASE' => $driver,
        'TIA_DATABASE_IMAGE' => "{$driver}:1",
        'TIA_DATABASE_PORT' => (string) $port,
        'TIA_DATABASE_NAME' => 'testing',
        'TIA_DATABASE_USERNAME' => $username,
        'TIA_DATABASE_PASSWORD' => $password,
    ];
}

function redisInputs(int $port, string $password = ''): array
{
    return [
        'TIA_REDIS' => 'true',
        'TIA_REDIS_IMAGE' => 'redis:7',
        'TIA_REDIS_PORT' => (string) $port,
        'TIA_REDIS_PASSWORD' => $password,
    ];
}

function freePort(): int
{
    $server = listeningSocket();
    $port = socketPort($server);
    fclose($server);

    return $port;
}

function listeningSocket(): mixed
{
    return stream_socket_server('tcp://127.0.0.1:0');
}

function socketPort(mixed $server): int
{
    $address = (string) stream_socket_get_name($server, false);

    return (int) substr((string) strrchr($address, ':'), 1);
}

function permissions(string $path): int
{
    return fileperms($path) & MODE_WORLD_WRITABLE;
}
