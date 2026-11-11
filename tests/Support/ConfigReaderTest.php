<?php

declare(strict_types=1);

use Composer\Util\ErrorHandler;
use MikeBronner\DevelopmentSettings\Support\ConfigReader;
use MikeBronner\DevelopmentSettings\Support\PackageConfig;
use MikeBronner\DevelopmentSettings\Support\SystemProcess;

const READ_AFTER_REWRITE = <<<PHP
    require %s;
    %s::register();
    \$configFile = %s;
    \$earlier = require \$configFile;
    file_put_contents(\$configFile, %s);
    \$isStale = (require \$configFile) === \$earlier;
    \$config = (new %s)->read(%s);
    echo json_encode(['stale' => \$isStale, 'paths' => \$config->paths()]);
    PHP;

beforeEach(function (): void {
    $this->package = makeTempDir();
    seedFiles($this->package, [
        PackageConfig::FILE => "<?php return ['paths' => ['files' => ['old.md']]];",
    ]);
});

afterEach(function (): void {
    removeTempDir($this->package);
});

it('reads the package config', function (): void {
    expect((new ConfigReader)->read($this->package)->paths())->toBe(['files' => ['old.md']]);
});

it('reads the config on disk, not a copy OPcache compiled earlier in the run', function (
    string $restriction,
): void {
    $script = sprintf(
            READ_AFTER_REWRITE,
            var_export(REPOSITORY_ROOT . '/vendor/autoload.php', true),
            ErrorHandler::class,
            var_export("{$this->package}/" . PackageConfig::FILE, true),
            var_export("<?php return ['paths' => ['files' => ['new.md']]];", true),
            ConfigReader::class,
            var_export($this->package, true),
        );

    $result = (new SystemProcess)->capture(cachingPhpCommand($script, $restriction));

    expect(json_decode($result->output(), associative: true))
        ->toBe(['stale' => true, 'paths' => ['files' => ['new.md']]], $result->output());
})->with(OPCACHE_API)
    ->skip(! function_exists('opcache_invalidate'), NO_OPCACHE);
