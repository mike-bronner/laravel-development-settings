<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\IgnoreOverrides;
use MikeBronner\DevelopmentSettings\Support\ManagedSection;
use MikeBronner\DevelopmentSettings\Support\SystemProcess;

beforeEach(function (): void {
    $this->project = makeTempDir('devset-gitignore-');
    $this->shipped = shippedSource('resources/project/gitignore');
    $this->process = new SystemProcess;
    $this->process
        ->run('git init -q', $this->project);
    $this->ignoredByGit = fn (string $path): bool => $this->process
        ->capture(
            'git check-ignore -q --no-index ' . escapeshellarg($path),
            $this->project,
        )->exitCode() === 0;
});

afterEach(function (): void {
    removeTempDir($this->project);
});

it('keeps agent files, .ai and placeholders, and ignores what is generated', function (): void {
    $placeholders = [
        'bootstrap/cache/.gitignore',
        'storage/framework/.gitignore',
        'storage/framework/cache/.gitignore',
        'storage/framework/cache/data/.gitignore',
        'storage/framework/sessions/.gitignore',
        'storage/framework/testing/.gitignore',
        'storage/framework/views/.gitignore',
        'storage/logs/.gitignore',
    ];
    $generated = [
        'bootstrap/cache/packages.php',
        'bootstrap/cache/services.php',
        'storage/framework/cache/data/ab/cd/entry',
        'storage/framework/sessions/session',
        'storage/framework/testing/disks/file',
        'storage/framework/views/compiled.php',
        'storage/logs/laravel.log',
        'boost.json',
        'GEMINI.md',
    ];
    $kept = ['.ai/guidelines/team.md', 'AGENTS.md', 'CLAUDE.md', ...$placeholders];
    seedFiles($this->project, [
        '.gitignore' => $this->shipped,
        ...collect([...$kept, ...$generated])
            ->mapWithKeys(fn (string $path): array => [$path => "x\n"])
            ->all(),
    ]);

    $this->process
        ->run('git add -A', $this->project);
    $tracked = $this->process
        ->capture('git ls-files', $this->project)
        ->output();

    expect(explode("\n", trim($tracked)))->toEqualCanonicalizing(['.gitignore', ...$kept]);
});

it('leaves tracked all the override check treats as tracked on purpose', function (): void {
    file_put_contents("{$this->project}/.gitignore", $this->shipped);

    $ignored = collect(IgnoreOverrides::TRACKED_ON_PURPOSE)->filter($this->ignoredByGit);

    expect($ignored->all())->toBe([]);
});

it('names a tracked path exactly when git ignores it', function (string $line): void {
    $gitignore = (new ManagedSection)->compose(managed: $this->shipped, project: "{$line}\n");
    file_put_contents("{$this->project}/.gitignore", $gitignore);
    $reasons = collect((new IgnoreOverrides)->find($gitignore, $this->shipped))
        ->flatMap(fn (array $override): array => data_get($override, 1))
        ->all();

    $named = collect(IgnoreOverrides::TRACKED_ON_PURPOSE)
        ->keys()
        ->filter(fn (string $label): bool => in_array(
                sprintf(IgnoreOverrides::IGNORES, $label),
                $reasons,
                strict: true,
            ))
        ->values()
        ->all();
    $ignoredByGit = collect(IgnoreOverrides::TRACKED_ON_PURPOSE)
        ->filter($this->ignoredByGit)
        ->keys()
        ->all();

    expect($named)->toBe($ignoredByGit);
})->with([
    '.ai', '/.ai/', '.ai/*', '.ai/guidelines', 'AGENTS.md', '/CLAUDE.md', '*.md', 'CLAUDE.m?',
    'CLAUDE.md/', '/docs/CLAUDE.md', 'AGENTS.md.bak', '/bootstrap/cache', '/bootstrap/cache/*',
    '/storage/framework', '/storage/framework/**', '/storage/framework/views/', '/storage/logs/*',
    '**/storage/logs/.gitignore', '.gitignore', 'storage', '/storage/framework/**/', 'cache',
    'a parent ignored, the file re-included' => ["/storage/*\n!/storage/logs/.gitignore"],
    'a directory ignored, its child re-included' => [".ai/\n!.ai/guidelines/"],
    'a directory ignored, then re-included' => [".ai/\n!.ai/"],
    'an ignore undone' => ["AGENTS.md\n!AGENTS.md"],
    'an escaped negation' => ["AGENTS.md\n\\!AGENTS.md"],
    'an escaped hash' => ['\\#AGENTS.md'],
    'trailing spaces' => ['AGENTS.md   '],
    'an escaped trailing space' => ['AGENTS.md\\ '],
    'a hash in a class' => ['[#]x'],
    'a range out of order' => ['CLAUDE.m[z-a]'],
    'a bracket as the class' => ['[]]'],
    'a negated class' => ['[!x]GENTS.md'],
    'a double star between' => ['/storage/**/.gitignore'],
]);
