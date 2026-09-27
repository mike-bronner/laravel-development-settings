<?php

declare(strict_types=1);

/*
 * Tracked files reach a project through the Composer plugin alone. A Laravel
 * service provider publishing them would copy each one whole, over the
 * project's lines below the marker of a managed target, and past every check
 * `FileSync` makes before it writes. Nothing registered the one this package
 * used to carry, so it was removed rather than taught the marker.
 *
 * The sources are read as text, not through Pest's arch plugin: that plugin
 * raises a deprecation on PHP 8.5, and the suite runs clean without it.
 */
it('ships no Laravel service provider', function (): void {
    $sources = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(REPOSITORY_ROOT . '/src', FilesystemIterator::SKIP_DOTS),
    );
    $providers = collect(iterator_to_array($sources))
        ->keys()
        ->filter(fn (string $path): bool => str_contains(
            (string) file_get_contents($path),
            'Illuminate\Support\ServiceProvider',
        ))
        ->all();

    expect($providers)->toBe([]);
});
