<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;

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
