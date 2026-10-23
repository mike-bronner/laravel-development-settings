<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\ManagedSection;

const MARKERS_WHEN_DOUBLED = 2;

it('splits at the marker', function (string $contents, string $managed, string $project): void {
    $section = new ManagedSection;

    expect($section->split($contents))->toBe(['managed' => $managed, 'project' => $project])
        ->and($section->managedPart($contents))
        ->toBe($managed)
        ->and($section->projectPart($contents))
        ->toBe($project);
})->with([
    'project lines below' => [
        marked("/vendor\n.env\n", "\n!AGENTS.md\n/deprecations.log\n"),
        "/vendor\n.env\n",
        "!AGENTS.md\n/deprecations.log\n",
    ],
    'ending at the marker and its newline' => [marked("/vendor\n"), "/vendor\n", ''],
    'ending at the marker, with no newline' => [marked("/vendor\n", ''), "/vendor\n", ''],
    'trailing whitespace and a carriage return' => [
        marked("/vendor\r\n", " \r\nphpunit.xml\r\n"),
        "/vendor\r\n",
        "phpunit.xml\r\n",
    ],
]);

it('splits nothing unless one line is the marker', function (string $contents, int $markers): void {
    $section = new ManagedSection;

    expect([
        $section->split($contents),
        $section->managedPart($contents),
        $section->projectPart($contents),
    ])->toBe([null, null, null])
        ->and($section->markers($contents))
        ->toBe($markers);
})->with([
    'no marker' => ["/vendor\n.env\n", 0],
    'the marker twice' => [marked("/vendor\n", "\n.env\n") . marked(''), MARKERS_WHEN_DOUBLED],
    'only lines that quote the marker' => [marked('see: ', "\n") . marked('', " extra\n"), 0],
]);

it('composes the source, the marker and the project part', function (): void {
    $section = new ManagedSection;
    $composed = $section->compose(managed: "/vendor\n", project: "!AGENTS.md\n");

    expect($composed)->toBe(marked("/vendor\n", "\n!AGENTS.md\n"))
        ->and($section->split($composed))
        ->toBe(['managed' => "/vendor\n", 'project' => "!AGENTS.md\n"]);
});

it('composes an empty source as the marker alone', function (): void {
    expect((new ManagedSection)->compose(managed: '', project: "!AGENTS.md\n"))
        ->toBe(marked('', "\n!AGENTS.md\n"));
});

it('refuses a source that does not end with a newline', function (): void {
    (new ManagedSection)->compose(managed: '/vendor', project: '');
})->throws(LogicException::class, 'must end with a newline');

it('names the package and says where project entries go and what is lost', function (): void {
    $expected = <<<MARKER
        # mike-bronner/laravel-development-settings: project entries go below this line.
        MARKER;

    expect(ManagedSection::MARKER)->toBe("{$expected} Anything above it is lost on the next sync.");
});

it('splits a project-first file at its marker', function (
    string $contents,
    string $project,
    string $managed,
): void {
    $section = new ManagedSection(project: ManagedSection::PROJECT_ABOVE);

    expect($section->split($contents))->toBe(['managed' => $managed, 'project' => $project])
        ->and($section->managedPart($contents))
        ->toBe($managed)
        ->and($section->projectPart($contents))
        ->toBe($project);
})->with([
    'project lines above' => [
        markedFirst("env:\n  KEY: value\n\n", "\nname: CI\n"),
        "env:\n  KEY: value\n\n",
        "name: CI\n",
    ],
    'starting at the marker' => [markedFirst('', "\nname: CI\n"), '', "name: CI\n"],
    'trailing whitespace and a carriage return' => [
        markedFirst("env: {}\r\n", " \r\nname: CI\r\n"),
        "env: {}\r\n",
        "name: CI\r\n",
    ],
]);

it('reads only its own marker', function (string $project, string $contents): void {
    $section = new ManagedSection(project: $project);

    expect($section->markers($contents))->toBe(0)
        ->and($section->split($contents))
        ->toBeNull();
})->with([
    'a project-first file with the bottom marker' => [
        ManagedSection::PROJECT_ABOVE,
        marked("env: {}\n", "\nname: CI\n"),
    ],
    'a bottom file with the project-first marker' => [
        ManagedSection::PROJECT_BELOW,
        markedFirst("/vendor\n"),
    ],
]);

it('composes the project part, the marker and the source', function (string $project): void {
    $section = new ManagedSection(project: ManagedSection::PROJECT_ABOVE);
    $composed = $section->compose(managed: 'name: CI', project: $project);

    expect($composed)->toBe(markedFirst($project, "\nname: CI"))
        ->and($section->split($composed))
        ->toBe(['managed' => 'name: CI', 'project' => $project]);
})->with(['an env block' => ["env:\n  KEY: value\n"], 'nothing' => ['']]);

it('says project entries go above the project-first marker', function (): void {
    $expected = <<<MARKER
        # mike-bronner/laravel-development-settings: project entries go above this line.
        MARKER;

    expect(ManagedSection::PROJECT_FIRST_MARKER)
        ->toBe("{$expected} Anything below it is lost on the next sync.")
        ->and((new ManagedSection(project: ManagedSection::PROJECT_ABOVE))->marker())
        ->toBe(ManagedSection::PROJECT_FIRST_MARKER)
        ->and((new ManagedSection)->marker())
        ->toBe(ManagedSection::MARKER);
});
