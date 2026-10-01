<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Laravel;

use Illuminate\Support\ServiceProvider;
use MikeBronner\DevelopmentSettings\Support\PhpGuideline;

final class GuidelineServiceProvider extends ServiceProvider
{
    private const EXCLUDE = 'boost.guidelines.exclude';

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
    }
}
