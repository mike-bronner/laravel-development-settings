<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Laravel\Boost\Install\GuidelineAssist;
use Laravel\Boost\Install\GuidelineConfig;
use MikeBronner\DevelopmentSettings\Tests\Fixtures\RootedApp;

const FLARE_GUIDELINE = REPOSITORY_ROOT . '/resources/boost/guidelines/07-flare.blade.php';

const FLARE_HEADING = <<<MARKDOWN
    # Flare
    MARKDOWN;

beforeEach(function (): void {
    $this->project = makeTempDir('devset-flare-guideline-');
    Container::setInstance(new RootedApp($this->project));
    $this->render = function (array $boost = ['hasMcp' => true]): string {
        $config = new GuidelineConfig;
        $config->hasMcp = data_get($boost, 'hasMcp');
        $assist = (new ReflectionClass(GuidelineAssist::class))->newInstanceWithoutConstructor();
        $assist->config = $config;
        $compiler = new BladeCompiler(new Filesystem, $this->project);
        $compiled = "{$this->project}/guideline.php";
        file_put_contents(
                $compiled,
                $compiler->compileString((string) file_get_contents(FLARE_GUIDELINE)),
            );
        ob_start();
        require $compiled;

        return trim((string) ob_get_clean());
    };
});

afterEach(function (): void {
    Container::setInstance(null);
    removeTempDir($this->project);
});

it('tells agents to use the flare MCP server when a Flare client is installed', function (
    string $client,
): void {
    seedFiles($this->project, ["vendor/{$client}/composer.json" => '{}']);

    $guideline = ($this->render)();

    expect($guideline)->toStartWith(FLARE_HEADING)
        ->toContain(
            'Use the `flare` MCP server when you investigate a production error',
            'this guideline names none',
            'list the projects through the `flare` server',
            'ask the user',
            'Never ask for a Flare API token',
        );
})->with(['spatie/laravel-flare', 'spatie/flare-client-php']);

it('renders nothing, so Boost composes nothing, without a Flare client', function (): void {
    seedFiles($this->project, ['vendor/spatie/laravel-ignition/composer.json' => '{}']);

    expect(($this->render)())->toBe('');
});

it('renders nothing when Boost configured no MCP, though a Flare client is installed', function (
    string $client,
): void {
    seedFiles($this->project, ["vendor/{$client}/composer.json" => '{}']);

    expect(($this->render)(['hasMcp' => false]))->toBe('');
})->with(['spatie/laravel-flare', 'spatie/flare-client-php']);
