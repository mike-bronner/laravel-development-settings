<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Laravel;

use Illuminate\Support\ServiceProvider;
use MikeBronner\DevelopmentSettings\Support\ClaudeImport;
use MikeBronner\DevelopmentSettings\Support\PhpGuideline;

final class GuidelineServiceProvider extends ServiceProvider
{
    private const EXCLUDE = 'boost.guidelines.exclude';

    /**
     * Boost 2.10 composes Claude Code's guidelines into the file this key
     * names (`ClaudeCode::guidelinesPath()`), and treats an empty value as
     * unset, so an empty one is replaced too.
     */
    private const CLAUDE_CODE_GUIDELINES_PATH = 'boost.agents.claude_code.guidelines_path';

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
            true => $config->set(self::CLAUDE_CODE_GUIDELINES_PATH, ClaudeImport::TARGET),
            false => null,
        };
    }
}
