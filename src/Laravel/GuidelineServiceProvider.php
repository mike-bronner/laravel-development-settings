<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Laravel;

use Illuminate\Support\ServiceProvider;
use Laravel\Boost\Mcp\ToolExecutor;
use MikeBronner\DevelopmentSettings\Support\ClaudeRemoval;
use MikeBronner\DevelopmentSettings\Support\PhpGuideline;
use MikeBronner\DevelopmentSettings\Support\ProjectKind;
use MikeBronner\DevelopmentSettings\Support\RootedTestbench;

final class GuidelineServiceProvider extends ServiceProvider
{
    private const EXCLUDE = 'boost.guidelines.exclude';

    private const CLAUDE_CODE_GUIDELINES_PATH = 'boost.agents.claude_code.guidelines_path';

    public function register(): void
    {
        $artisan = $this->app
            ->basePath(ProjectKind::ARTISAN);
        $script = $this->app
            ->basePath(RootedTestbench::SCRIPT);

        match (! is_file($artisan) && is_file($script)) {
            true => $this->runToolsThrough($artisan, $script),
            false => null,
        };
    }

    public function boot(): void
    {
        $config = $this->app
            ->make('config');
        $excluded = collect($config->get(self::EXCLUDE, []))
            ->push(PhpGuideline::BOOST_KEY)
            ->unique()
            ->values()
            ->all();

        $config->set(self::EXCLUDE, $excluded);
        $guidelinesPath = $config->get(self::CLAUDE_CODE_GUIDELINES_PATH);

        match (empty($guidelinesPath)) {
            true => $config->set(self::CLAUDE_CODE_GUIDELINES_PATH, ClaudeRemoval::GUIDELINES),
            false => null,
        };
    }

    private function runToolsThrough(string $artisan, string $script): void
    {
        $executor = fn (): RootedToolExecutor => new RootedToolExecutor($artisan, $script);

        $this->app
            ->bind(ToolExecutor::class, $executor);
        $this->commands([McpExecutorCheck::class]);
    }
}
