<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\ManagedSection;

it('splits at the marker into the package part and the project part', function (): void {
    $contents = "/vendor\n.env\n" . ManagedSection::MARKER . "\n!AGENTS.md\n/deprecations.log\n";

    expect(ManagedSection::split($contents))->toBe([
        'managed' => "/vendor\n.env\n",
        'project' => "!AGENTS.md\n/deprecations.log\n",
    ]);
});

it('splits a file that ends at the marker, with or without a newline', function (string $ending): void {
    expect(ManagedSection::split("/vendor\n" . ManagedSection::MARKER . $ending))
        ->toBe(['managed' => "/vendor\n", 'project' => '']);
})->with(['newline' => "\n", 'no newline' => '']);

it('recognises a marker line with trailing whitespace or a carriage return', function (): void {
    $contents = "/vendor\r\n" . ManagedSection::MARKER . " \r\nphpunit.xml\r\n";

    expect(ManagedSection::split($contents))->toBe([
        'managed' => "/vendor\r\n",
        'project' => "phpunit.xml\r\n",
    ]);
});

it('does not split a file without the marker', function (): void {
    expect(ManagedSection::split("/vendor\n.env\n"))->toBeNull()
        ->and(ManagedSection::markers("/vendor\n.env\n"))->toBe(0);
});

it('does not split a file holding the marker twice', function (): void {
    $contents = "/vendor\n" . ManagedSection::MARKER . "\n.env\n" . ManagedSection::MARKER . "\nphpunit.xml\n";

    expect(ManagedSection::split($contents))->toBeNull()
        ->and(ManagedSection::markers($contents))->toBe(2);
});

it('counts only whole marker lines, never a line that quotes the marker', function (): void {
    $contents = '# see: ' . ManagedSection::MARKER . "\n" . ManagedSection::MARKER . " extra\n";

    expect(ManagedSection::markers($contents))->toBe(0)
        ->and(ManagedSection::split($contents))->toBeNull();
});

it('composes the source, the marker and the project part, and splits back to them', function (): void {
    $composed = ManagedSection::compose(managed: "/vendor\n", project: "!AGENTS.md\n");

    expect($composed)->toBe("/vendor\n" . ManagedSection::MARKER . "\n!AGENTS.md\n")
        ->and(ManagedSection::split($composed))->toBe(['managed' => "/vendor\n", 'project' => "!AGENTS.md\n"]);
});

it('refuses a source that does not end with a newline', function (): void {
    ManagedSection::compose(managed: '/vendor', project: '');
})->throws(LogicException::class, 'must end with a newline');

it('names the package and says where project entries go and what is lost', function (): void {
    expect(ManagedSection::MARKER)->toStartWith('# ')
        ->toContain('mike-bronner/laravel-development-settings')
        ->toContain('project entries go below this line')
        ->toContain('Anything above it is lost on the next sync');
});
