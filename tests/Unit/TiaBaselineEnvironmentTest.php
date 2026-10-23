<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

afterEach(function (): void {
    isset($this->project) && removeTempDir($this->project);
});

it('sets up a project shaped like crossbible with no hook', function (): void {
    expect(detectedEnvironment(crossbibleShapedProject(), CROSSBIBLE_ENV_BLOCK))->toBe([
        ...NO_ENVIRONMENT,
        'database' => 'pgsql',
        'database-image' => 'postgres:17',
        'database-port' => '5432',
        'database-name' => 'testing',
        'database-username' => 'postgres',
        'database-password' => 'postgres',
        'redis' => 'true',
        'redis-image' => 'redis:7',
        'redis-port' => '6379',
        'env-file' => 'true',
        'npm' => 'true',
        'build' => 'true',
        'browsers' => 'true',
        'writable' => 'storage bootstrap/cache',
        'php-extensions' => 'pdo_pgsql, redis',
    ]);
});

it('detects nothing in a project with nothing to set up', function (array $environment): void {
    expect(detectedEnvironment([], $environment))->toBe(NO_ENVIRONMENT);
})->with([
    'no env block' => [[]],
    'an env block with secrets alone' => [['NOVA_USERNAME' => 'nova', 'NOVA_LICENSE_KEY' => 'key']],
]);

it('keeps the extensions of the before-install hook', function (
    array $files,
    string $hookExtensions,
    string $expected,
): void {
    $environment = ['HOOK_PHP_EXTENSIONS' => $hookExtensions];

    expect(detectedSettings(['php-extensions'], $files, $environment))
        ->toBe(['php-extensions' => $expected]);
})->with([
    'none' => [connection('pgsql'), '', 'pdo_pgsql'],
    'others' => [connection('pgsql'), 'imagick, intl', 'imagick, intl, pdo_pgsql'],
    'the needed one already' => [connection('pgsql'), 'imagick, PDO_PGSQL', 'imagick, PDO_PGSQL'],
    'no need of its own' => [[], 'imagick, intl', 'imagick, intl'],
]);

it('reads the connection as the test run sees it', function (
    array $files,
    array $environment,
    string $expected,
): void {
    expect(detectedSettings(['database'], $files, $environment))->toBe(['database' => $expected]);
})->with([
    'a phpunit server entry, over the process' => [
        phpunitFile(server: ['DB_CONNECTION' => 'mysql']),
        ['DB_CONNECTION' => 'pgsql'],
        'mysql',
    ],
    'the process, over a phpunit env entry' => [
        connection('mysql'),
        ['DB_CONNECTION' => 'pgsql'],
        'pgsql',
    ],
    'a phpunit env entry, over .env.example' => [
        [
            ...phpunitFile(['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:']),
            ...exampleFile(['DB_CONNECTION' => 'mysql']),
        ],
        [],
        'sqlite',
    ],
    '.env.example alone' => [exampleFile(['DB_CONNECTION' => 'mysql']), [], 'mysql'],
    'phpunit.xml.dist when there is no phpunit.xml' => [
        ['phpunit.xml.dist' => phpunitXml(['DB_CONNECTION' => 'mariadb'])],
        [],
        'mariadb',
    ],
    'phpunit.xml over phpunit.xml.dist' => [
        [...connection('pgsql'), 'phpunit.xml.dist' => phpunitXml(['DB_CONNECTION' => 'mysql'])],
        [],
        'pgsql',
    ],
    'not a commented-out entry' => [['phpunit.xml' => COMMENTED_PHPUNIT], [], ''],
    'not a commented-out line' => [['.env.example' => COMMENTED_ENV_EXAMPLE], [], 'sqlite'],
]);

it('reads the driver from the connection name', function (string $name, string $expected): void {
    expect(detectedSettings(['database'], connection($name)))->toBe(['database' => $expected]);
})->with([
    ['pgsql', 'pgsql'],
    ['pgsql-testing', 'pgsql'],
    ['postgres', 'pgsql'],
    ['mysql', 'mysql'],
    ['MySQL_tests', 'mysql'],
    ['mariadb', 'mariadb'],
    ['sqlite', 'sqlite'],
    ['testing', ''],
]);

it('serves each database server on its default image and credentials', function (
    string $name,
    array $expected,
): void {
    expect(detectedSettings(array_keys($expected), connection($name)))->toBe($expected);
})->with([
    'PostgreSQL' => ['pgsql', [
        'database-image' => 'postgres:17',
        'database-port' => '5432',
        'database-name' => 'testing',
        'database-username' => 'postgres',
        'database-password' => 'postgres',
        'php-extensions' => 'pdo_pgsql',
    ]],
    'MySQL' => ['mysql', [
        'database-image' => 'mysql:8.4',
        'database-port' => '3306',
        'database-name' => 'testing',
        'database-username' => 'root',
        'database-password' => 'password',
        'php-extensions' => 'pdo_mysql',
    ]],
    'MariaDB' => ['mariadb', [
        'database-image' => 'mariadb:11',
        'database-port' => '3306',
        'database-name' => 'testing',
        'database-username' => 'root',
        'database-password' => 'password',
        'php-extensions' => 'pdo_mysql',
    ]],
]);

it('lets the env block choose each server version', function (
    string $name,
    string $variable,
    string $image,
): void {
    expect(detectedSettings(['database-image'], connection($name), [$variable => '15']))
        ->toBe(['database-image' => $image]);
})->with([
    ['pgsql', 'TIA_POSTGRES_VERSION', 'postgres:15'],
    ['mysql', 'TIA_MYSQL_VERSION', 'mysql:15'],
    ['mariadb', 'TIA_MARIADB_VERSION', 'mariadb:15'],
]);

it('applies every default the documented env block changes', function (): void {
    $documented = collect((array) data_get(Yaml::parse(TIA_OVERRIDE_ENV), 'env'))
        ->map(fn (mixed $value): string => (string) $value)
        ->all();
    $environment = [...$documented, 'REDIS_HOST' => '127.0.0.1'];
    $expected = [
        'database-image' => 'postgres:16',
        'database-name' => 'app_testing',
        'database-username' => 'app',
        'database-password' => 'secret',
        'redis-image' => 'redis:7.4',
    ];

    expect(detectedSettings(array_keys($expected), connection('pgsql'), $environment))
        ->toBe($expected);
});

it('lets the env block choose the Redis version', function (): void {
    $environment = ['REDIS_HOST' => '127.0.0.1', 'TIA_REDIS_VERSION' => '8'];

    expect(detectedSettings(['redis-image'], [], $environment))->toBe(['redis-image' => 'redis:8']);
});

it('takes the database credentials the test run reads', function (
    array $files,
    array $environment,
    array $expected,
): void {
    $keys = ['database-port', 'database-name', 'database-username', 'database-password'];

    expect(array_values(detectedSettings($keys, $files, $environment)))->toBe($expected);
})->with([
    'from .env.example, quoted or with a comment' => [
        ['.env.example' => QUOTED_ENV_EXAMPLE],
        [],
        ['5433', 'app_tests', 'sail', 'secret'],
    ],
    'from the env block, over phpunit.xml' => [
        phpunitFile(['DB_CONNECTION' => 'pgsql', 'DB_USERNAME' => 'root']),
        ['DB_PORT' => '6543', 'DB_DATABASE' => 'ci', 'DB_USERNAME' => 'ci', 'DB_PASSWORD' => 'pw'],
        ['6543', 'ci', 'ci', 'pw'],
    ],
    'an empty password, kept empty' => [
        phpunitFile(['DB_CONNECTION' => 'mysql', 'DB_PASSWORD' => '']),
        [],
        ['3306', 'testing', 'root', ''],
    ],
    'a null password, as an empty one' => [
        exampleFile(['DB_CONNECTION' => 'pgsql', 'DB_PASSWORD' => 'null']),
        [],
        ['5432', 'testing', 'postgres', ''],
    ],
]);

it('creates a file-based SQLite database only when it is missing', function (
    array $files,
    string $expected,
): void {
    expect(detectedSettings(['sqlite-file', 'php-extensions'], $files))
        ->toBe(['sqlite-file' => $expected, 'php-extensions' => 'pdo_sqlite']);
})->with([
    'in memory' => [phpunitFile(['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:']), ''],
    'the default file' => [['.env.example' => COMMENTED_ENV_EXAMPLE], 'database/database.sqlite'],
    'a named file' => [
        exampleFile(['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => 'tests.sqlite']),
        'tests.sqlite',
    ],
    'a file the repository holds' => [
        ['.env.example' => COMMENTED_ENV_EXAMPLE, 'database/database.sqlite' => ''],
        '',
    ],
]);

it('starts Redis only when a driver uses it or the test settings name it', function (
    array $files,
    array $environment,
    string $expected,
): void {
    expect(detectedSettings(['redis'], $files, $environment))->toBe(['redis' => $expected]);
})->with([
    'the stock Laravel skeleton' => [
        ['.env.example' => SKELETON_ENV_EXAMPLE, 'phpunit.xml' => SKELETON_PHPUNIT],
        [],
        '',
    ],
    'a REDIS_ variable in .env.example alone' => [
        exampleFile(['REDIS_HOST' => '127.0.0.1']),
        [],
        '',
    ],
    'a REDIS_ variable in a phpunit env entry' => [phpunitFile(['REDIS_DB' => '3']), [], 'true'],
    'a REDIS_ variable in a phpunit server entry' => [
        phpunitFile(server: ['REDIS_HOST' => 'cache']),
        [],
        'true',
    ],
    'a REDIS_ variable in the env block' => [[], ['REDIS_HOST' => '127.0.0.1'], 'true'],
    'a driver set to redis in phpunit.xml' => [
        phpunitFile(['QUEUE_CONNECTION' => 'redis']),
        [],
        'true',
    ],
    'a driver set to redis in the env block' => [[], ['CACHE_STORE' => 'Redis'], 'true'],
    'a driver set to redis in .env.example, overridden in phpunit.xml' => [
        [
            ...exampleFile(['SESSION_DRIVER' => 'redis']),
            ...phpunitFile(['SESSION_DRIVER' => 'array']),
        ],
        [],
        'true',
    ],
    'a client named phpredis alone' => [exampleFile(['REDIS_CLIENT' => 'phpredis']), [], ''],
    'no mention' => [exampleFile(['CACHE_STORE' => 'database']), [], ''],
]);

it('serves Redis on the port, password and client the project names', function (
    array $settings,
    array $expected,
): void {
    $keys = ['redis-port', 'redis-password', 'php-extensions'];

    $files = exampleFile([...$settings, 'CACHE_STORE' => 'redis']);

    expect(array_values(detectedSettings($keys, $files)))->toBe($expected);
})->with([
    'the defaults' => [
        ['REDIS_HOST' => '127.0.0.1', 'REDIS_PASSWORD' => 'null'],
        ['6379', '', 'redis'],
    ],
    'its own' => [
        ['REDIS_PORT' => '6380', 'REDIS_PASSWORD' => 'secret', 'REDIS_CLIENT' => 'phpredis'],
        ['6380', 'secret', 'redis'],
    ],
    'predis, with no extension' => [
        ['REDIS_HOST' => '127.0.0.1', 'REDIS_CLIENT' => 'predis'],
        ['6379', '', ''],
    ],
]);

it('copies .env.example only when there is no .env', function (
    array $files,
    string $expected,
): void {
    expect(detectedSettings(['env-file'], $files))->toBe(['env-file' => $expected]);
})->with([
    'no .env' => [exampleFile(['APP_NAME' => 'Laravel']), 'true'],
    'a .env already' => [[...exampleFile(['APP_NAME' => 'Laravel']), '.env' => ''], ''],
    'no .env.example' => [[], ''],
]);

it('generates an application key only when the test run has none', function (
    array $files,
    array $environment,
    string $expected,
): void {
    expect(detectedSettings(['app-key'], $files, $environment))->toBe(['app-key' => $expected]);
})->with([
    'an empty key in .env.example' => [laravelApp(['APP_KEY' => '']), [], 'true'],
    'a key in .env.example' => [laravelApp(['APP_KEY' => 'base64:a']), [], ''],
    'a key in phpunit.xml' => [
        [...laravelApp(['APP_KEY' => '']), ...phpunitFile(['APP_KEY' => 'base64:a'])],
        [],
        '',
    ],
    'a key in the env block' => [laravelApp(['APP_KEY' => '']), ['APP_KEY' => 'base64:a'], ''],
    'no artisan' => [exampleFile(['APP_KEY' => '']), [], ''],
    'no APP_KEY line to write' => [laravelApp(['APP_NAME' => 'Laravel']), [], ''],
]);

it('installs and builds the front end with npm when the project locks it', function (
    array $files,
    array $expected,
): void {
    expect(array_values(detectedSettings(['npm', 'build'], $files)))->toBe($expected);
})->with([
    'a lock and a build script' => [
        ['package.json' => PACKAGE_WITH_BUILD, 'package-lock.json' => '{}'],
        ['true', 'true'],
    ],
    'a lock and no build script' => [
        ['package.json' => PACKAGE_WITHOUT_BUILD, 'package-lock.json' => '{}'],
        ['true', ''],
    ],
    'no npm lock' => [['package.json' => PACKAGE_WITH_BUILD, 'yarn.lock' => ''], ['', '']],
    'no package.json' => [['package-lock.json' => '{}'], ['', '']],
]);

it('installs the Playwright browser from the locked npm dependency', function (
    string $composer,
    string $lock,
): void {
    $result = detectionRun(npmProject($lock, $composer));

    expect(detectedOutputs())->toMatchArray(['browsers' => 'true']);
    expect($result->output())->not
        ->toContain('::warning');
})->with([
    'as a dev dependency' => [composerJson(requireDev: [BROWSER_PLUGIN]), PLAYWRIGHT_LOCK],
    'as a dependency' => [composerJson(require: [BROWSER_PLUGIN]), PLAYWRIGHT_LOCK],
    'in a version 1 lock' => [composerJson(requireDev: [BROWSER_PLUGIN]), PLAYWRIGHT_LOCK_V1],
]);

it('warns, and installs no browser, when npm locks no Playwright', function (array $files): void {
    $composer = composerJson(requireDev: [BROWSER_PLUGIN]);

    $result = detectionRun([...$files, 'composer.json' => $composer]);

    expect(detectedOutputs())->toMatchArray(['browsers' => '']);
    expect($result->output())->toContain(PLAYWRIGHT_WARNING);
})->with([
    'a lock without it' => [npmProject(LOCK_WITHOUT_PLAYWRIGHT)],
    'no npm lock' => [['package.json' => PACKAGE_WITH_BUILD, 'pnpm-lock.yaml' => '']],
    'no package.json' => [[]],
]);

it('installs no browser and warns of nothing without Pest browser tests', function (): void {
    $result = detectionRun(npmProject(PLAYWRIGHT_LOCK, composerJson(requireDev: ['pestphp/pest'])));

    expect(detectedOutputs())->toMatchArray(['browsers' => '']);
    expect($result->output())->not
        ->toContain('::warning');
});

it('says when it installs no front end for want of an npm lock', function (
    array $files,
    string $expected,
): void {
    $notices = collect(explode("\n", detectionRun($files)->output()))
        ->filter(fn (string $line): bool => str_starts_with($line, '::notice'))
        ->implode("\n");

    expect($notices)->toBe($expected);
})->with([
    'pnpm' => [
        ['package.json' => PACKAGE_WITH_BUILD, 'pnpm-lock.yaml' => ''],
        frontEndNotice(', only pnpm-lock.yaml'),
    ],
    'Yarn' => [
        ['package.json' => PACKAGE_WITH_BUILD, 'yarn.lock' => ''],
        frontEndNotice(', only yarn.lock'),
    ],
    'no lock at all' => [['package.json' => PACKAGE_WITH_BUILD], frontEndNotice('')],
    'an npm lock' => [npmProject('{}'), ''],
    'no package.json' => [['yarn.lock' => ''], ''],
]);

it('makes writable only the Laravel directories the repository holds', function (
    array $files,
    string $expected,
): void {
    expect(detectedSettings(['writable'], $files))->toBe(['writable' => $expected]);
})->with([
    'both' => [
        ['storage/.gitignore' => '', 'bootstrap/cache/.gitignore' => ''],
        'storage bootstrap/cache',
    ],
    'storage alone' => [['storage/.gitignore' => ''], 'storage'],
    'neither' => [[], ''],
]);

it('fails on a file it cannot read', function (
    string $file,
    string $contents,
    string $message,
): void {
    $this->project = makeTempDir('devset-tia-environment-');
    seedFiles($this->project, ['composer.json' => composerJson(), $file => $contents]);

    $result = runTiaEnvironmentStep($this->project);

    expect($result->exitCode())->toBe(1);
    expect($result->output())->toContain("::error title=Pest TIA baseline::{$message}");
    expect((string) file_get_contents("{$this->project}/github-output"))->toBe('');
})->with([
    ['phpunit.xml', BROKEN_PHPUNIT, 'phpunit.xml is not valid XML'],
    ['package.json', BROKEN_JSON, 'package.json is not a JSON object.'],
    ['composer.json', BROKEN_JSON, 'composer.json is not a JSON object.'],
]);

it('prints what it found and never a password', function (): void {
    $this->project = makeTempDir('devset-tia-environment-');
    seedFiles($this->project, crossbibleShapedProject());
    $environment = [...CROSSBIBLE_ENV_BLOCK, 'DB_PASSWORD' => 'hunter2'];

    $result = runTiaEnvironmentStep($this->project, $environment);

    expect($result->output())->toContain('database-image: postgres:17', 'redis-image: redis:7');
    expect($result->output())->not
        ->toContain('hunter2');
});

it('starts the database, waits for it, and points the test run at it', function (): void {
    $this->project = makeTempDir('devset-tia-services-');
    $port = freePort();
    $inputs = databaseInputs('pgsql', $port, 'postgres', 'postgres');

    $result = runTiaServicesStep($this->project, $inputs);

    expect($result->exitCode())->toBe(0, $result->output());
    expect(dockerCalls($this->project))->toBe([
        [
            'run', '--detach', '--name', 'tia-database', '--publish', "{$port}:5432",
            '--env', 'POSTGRES_DB=testing', '--env', 'POSTGRES_USER=postgres',
            '--env', 'POSTGRES_PASSWORD=postgres',
            '--health-cmd', 'pg_isready -h 127.0.0.1', '--health-interval', '2s', 'pgsql:1',
        ],
        ['inspect', '--format', '{{.State.Health.Status}}', 'tia-database'],
    ]);
    expect(exportedEnvironment($this->project))->toBe(envExample([
        'DB_HOST' => '127.0.0.1',
        'DB_PORT' => (string) $port,
        'DB_DATABASE' => 'testing',
        'DB_USERNAME' => 'postgres',
        'DB_PASSWORD' => 'postgres',
    ]));
    expect($result->output())->toContain("Started database on port {$port}.");
});

it('creates the database user the test run connects as', function (
    string $driver,
    string $username,
    string $password,
    string $serverPort,
    array $expected,
): void {
    $this->project = makeTempDir('devset-tia-services-');
    $port = freePort();
    $inputs = databaseInputs($driver, $port, $username, $password);

    $result = runTiaServicesStep($this->project, $inputs);

    expect($result->exitCode())->toBe(0, $result->output());
    expect(dockerArguments($this->project))
        ->toContain(argumentRun(['--publish', "{$port}:{$serverPort}", ...$expected]));
})->with([
    'PostgreSQL with no password' => ['pgsql', 'root', '', '5432', [
        '--env', 'POSTGRES_DB=testing', '--env', 'POSTGRES_USER=root',
        '--env', 'POSTGRES_HOST_AUTH_METHOD=trust',
        '--health-cmd', 'pg_isready -h 127.0.0.1',
    ]],
    'MySQL root with no password' => ['mysql', 'root', '', '3306', [
        '--env', 'MYSQL_DATABASE=testing', '--env', 'MYSQL_ALLOW_EMPTY_PASSWORD=yes',
        '--health-cmd', 'mysqladmin ping -h 127.0.0.1 --silent',
    ]],
    'MySQL root with a password' => ['mysql', 'root', 'secret', '3306', [
        '--env', 'MYSQL_DATABASE=testing', '--env', 'MYSQL_ROOT_PASSWORD=secret',
        '--health-cmd',
    ]],
    'MariaDB, checked with its own script' => ['mariadb', 'root', '', '3306', [
        '--env', 'MYSQL_DATABASE=testing', '--env', 'MYSQL_ALLOW_EMPTY_PASSWORD=yes',
        '--health-cmd', 'healthcheck.sh --connect --innodb_initialized',
    ]],
]);

it('creates a database user of its own, with every privilege, password or none', function (
    string $driver,
    string $username,
    string $password,
    string $expected,
): void {
    $this->project = makeTempDir('devset-tia-services-');
    $inputs = databaseInputs($driver, freePort(), $username, $password);

    $result = runTiaServicesStep($this->project, $inputs);

    expect($result->exitCode())->toBe(0, $result->output());
    expect(dockerArguments($this->project))->toContain(argumentRun([
        '--env', 'MYSQL_DATABASE=testing',
        '--volume', "{$this->project}/runner-temp/tia-database-init:/docker-entrypoint-initdb.d:ro",
        '--env', 'MYSQL_RANDOM_ROOT_PASSWORD=yes',
        '--health-cmd',
    ]))->not
        ->toContain('MYSQL_USER');
    expect(databaseInitScript($this->project))->toBe($expected);
})->with([
    'MySQL with no password' => ['mysql', 'app', '', <<<SQL
        CREATE USER 'app'@'%' IDENTIFIED BY '';
        GRANT ALL PRIVILEGES ON *.* TO 'app'@'%';

        SQL],
    'MariaDB with no password' => ['mariadb', 'app', '', <<<SQL
        CREATE USER 'app'@'%' IDENTIFIED BY '';
        GRANT ALL PRIVILEGES ON *.* TO 'app'@'%';

        SQL],
    'MySQL with a password' => ['mysql', 'app', 'secret', <<<SQL
        CREATE USER 'app'@'%' IDENTIFIED BY 'secret';
        GRANT ALL PRIVILEGES ON *.* TO 'app'@'%';

        SQL],
    'quotes and backslashes, escaped' => ['mysql', "o'neil", 'a\b\'c', <<<SQL
        CREATE USER 'o''neil'@'%' IDENTIFIED BY 'a\\\\b''c';
        GRANT ALL PRIVILEGES ON *.* TO 'o''neil'@'%';

        SQL],
]);

it('writes no user script for root', function (string $driver): void {
    $this->project = makeTempDir('devset-tia-services-');

    $result = runTiaServicesStep($this->project, databaseInputs($driver, freePort(), 'root', ''));

    expect($result->exitCode())->toBe(0, $result->output());
    expect("{$this->project}/runner-temp/tia-database-init")->not
        ->toBeDirectory();
    expect(dockerArguments($this->project))->not
        ->toContain('--volume');
})->with(['mysql', 'mariadb']);

it('starts Redis with the password the project names, and checks with it', function (
    string $password,
    array $passwordOption,
    array $command,
): void {
    $this->project = makeTempDir('devset-tia-services-');
    $port = freePort();

    $result = runTiaServicesStep($this->project, redisInputs($port, $password));

    expect($result->exitCode())->toBe(0, $result->output());
    expect(data_get(dockerCalls($this->project), 0))->toBe([
        'run', '--detach', '--name', 'tia-redis', '--publish', "{$port}:6379",
        ...$passwordOption,
        '--health-cmd', 'redis-cli ping | grep -q PONG', '--health-interval', '2s', 'redis:7',
        ...$command,
    ]);
    expect(exportedEnvironment($this->project))
        ->toBe(envExample(['REDIS_HOST' => '127.0.0.1', 'REDIS_PORT' => (string) $port]));
})->with([
    'none' => ['', [], []],
    'a password' => [
        'two words',
        ['--env', 'REDISCLI_AUTH=two words'],
        ['redis-server', '--requirepass', 'two words'],
    ],
]);

it('leaves each setting the environment sets already to the environment', function (
    string $preset,
): void {
    $this->project = makeTempDir('devset-tia-services-');
    $databasePort = freePort();
    $redisPort = freePort();
    $inputs = [
        ...databaseInputs('pgsql', $databasePort),
        ...redisInputs($redisPort),
        $preset => 'preset',
    ];
    $exported = [
        'DB_HOST' => '127.0.0.1',
        'DB_PORT' => (string) $databasePort,
        'DB_DATABASE' => 'testing',
        'DB_USERNAME' => 'app',
        'DB_PASSWORD' => 'secret',
        'REDIS_HOST' => '127.0.0.1',
        'REDIS_PORT' => (string) $redisPort,
    ];

    $result = runTiaServicesStep($this->project, $inputs);

    expect($result->exitCode())->toBe(0, $result->output());
    expect(exportedEnvironment($this->project))
        ->toBe(envExample(collect($exported)->except($preset)->all()));
})->with([
    'DB_HOST',
    'DB_PORT',
    'DB_DATABASE',
    'DB_USERNAME',
    'DB_PASSWORD',
    'REDIS_HOST',
    'REDIS_PORT',
]);

it('waits while a server starts, and goes on once it is healthy', function (): void {
    $this->project = makeTempDir('devset-tia-services-');
    $health = ['starting', 'starting', 'healthy'];

    $result = runTiaServicesStep($this->project, databaseInputs('pgsql', freePort()), $health);

    expect($result->exitCode())->toBe(0, $result->output());
    expect(collect(dockerCalls($this->project))->where(0, 'inspect'))->toHaveCount(count($health));
    expect(sleeps($this->project))->toBe(['2', '2']);
    expect(exportedEnvironment($this->project))->toContain('DB_HOST=127.0.0.1');
});

it('starts no database when the step has only Redis to start', function (): void {
    $this->project = makeTempDir('devset-tia-services-');
    $inputs = ['TIA_DATABASE' => 'sqlite', ...redisInputs(freePort())];

    $result = runTiaServicesStep($this->project, $inputs);

    expect($result->exitCode())->toBe(0, $result->output());
    expect(dockerArguments($this->project))->toContain('tia-redis')
        ->not
        ->toContain('tia-database');
});

it('leaves a port in use to the server already on it', function (): void {
    $this->project = makeTempDir('devset-tia-services-');
    $server = listeningSocket();
    $port = socketPort($server);
    $inputs = [...databaseInputs('pgsql', $port), ...redisInputs($port)];

    $result = runTiaServicesStep($this->project, $inputs);
    fclose($server);

    expect($result->exitCode())->toBe(0, $result->output());
    expect(dockerCalls($this->project))->toBe([]);
    expect(exportedEnvironment($this->project))->toBe('');
    expect($result->output())->toContain(
            "Port {$port} is in use already, so the workflow starts no database.",
            "Port {$port} is in use already, so the workflow starts no redis.",
        );
});

it('fails with the container log when a server never becomes healthy', function (): void {
    $this->project = makeTempDir('devset-tia-services-');

    $result = runTiaServicesStep($this->project, databaseInputs('pgsql', freePort()), ['starting']);

    expect($result->exitCode())->toBe(1);
    expect(collect(dockerCalls($this->project))->where(0, 'inspect'))->toHaveCount(HEALTH_POLLS);
    expect(sleeps($this->project))->toBe(array_fill(0, HEALTH_POLLS, '2'));
    expect($result->output())->toContain(
            'the container log',
            '::error title=Pest TIA baseline::The database container did not become healthy',
        );
    expect(exportedEnvironment($this->project))->toBe('');
});

it('creates the SQLite file and its directory', function (): void {
    $this->project = makeTempDir('devset-tia-sqlite-');
    $environment = ['TIA_SQLITE_FILE' => 'database/tests/app.sqlite'];

    $result = runTiaStep(TIA_SQLITE_STEP, $this->project, $environment);

    expect($result->exitCode())->toBe(0, $result->output());
    expect("{$this->project}/database/tests/app.sqlite")->toBeFile();
});

it('makes every listed directory writable', function (): void {
    $this->project = makeTempDir('devset-tia-writable-');
    seedFiles($this->project, ['storage/logs/.keep' => '', 'bootstrap/cache/.keep' => '']);
    chmod("{$this->project}/storage/logs", MODE_LOCKED_DIRECTORY);
    $environment = ['TIA_WRITABLE' => 'storage bootstrap/cache'];

    $result = runTiaStep(TIA_WRITABLE_STEP, $this->project, $environment);

    expect($result->exitCode())->toBe(0, $result->output());
    expect(permissions("{$this->project}/storage/logs"))->toBe(MODE_WORLD_WRITABLE)
        ->and(permissions("{$this->project}/bootstrap/cache"))
        ->toBe(MODE_WORLD_WRITABLE);
});
