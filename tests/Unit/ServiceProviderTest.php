<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;

/*
 * Tracked files reach a project through the Composer plugin alone. A Laravel
 * service provider publishing them would copy each one whole, over the
 * project's lines below the marker of a managed target, and past every check
 * `FileSync` makes before it writes. The one provider this package ships only
 * changes Boost's config, so no provider may publish.
 *
 * The sources are read as text, not through Pest's arch plugin: that plugin
 * raises a deprecation on PHP 8.5, and the suite runs clean without it.
 */
it('ships no Laravel service provider that publishes files', function (): void {
    $sources = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(REPOSITORY_ROOT . '/src', FilesystemIterator::SKIP_DOTS),
        );
    $providers = collect(iterator_to_array($sources))
        ->keys()
        ->map(fn (string $path): string => (string) file_get_contents($path))
        ->filter(fn (string $source): bool => str_contains($source, ServiceProvider::class));
    $publishing = $providers
        ->filter(fn (string $source): bool => str_contains($source, "publishes"));

    expect($providers)->not
        ->toBeEmpty();
    expect($publishing->all())->toBe([]);
});
