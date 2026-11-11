<?php

declare(strict_types=1);

const SKILL_TRIGGERS = ['review', 'rewrite', 'issue', 'epic', 'Product Goal', 'labels'];

const ISSUE_FORBIDDEN_TEXT = [
    ';',
    '—',
    '–',
    'Lestrade',
    'Watson',
    'Holmes',
    'The Index',
    'workbench',
    'vault',
];

afterEach(function (): void {
    isset($this->project) && removeTempDir($this->project);
});

it('ships each issue template to every consuming project as a tracked file', function (
    string $path,
): void {
    expect(shippedFiles())->toHaveKey($path, $path);
    expect(shippedManifest()->isKnown($path, md5(shippedSource($path))))->toBeTrue();
})->with(ISSUE_TEMPLATES);

it('writes the templates into a consuming project on install', function (): void {
    $templates = collect(ISSUE_TEMPLATES)->values();
    [$this->project] = makeConsumer([
        'manifest' => $templates
            ->mapWithKeys(fn (string $path): array => [$path => [md5(shippedSource($path))]])
            ->all(),
        'sources' => $templates
            ->mapWithKeys(fn (string $path): array => [$path => shippedSource($path)])
            ->all(),
        'paths' => ['files' => $templates->all()],
    ]);

    $output = publishIn($this->project);

    foreach ($templates as $path) {
        expect(file_get_contents("{$this->project}/{$path}"))->toBe(shippedSource($path));
        expect($output)->toContain($path);
    }
});

it('gives each template the sections the skill prescribes for its kind', function (
    string $kind,
): void {
    expect(issueTemplateHeadings(data_get(ISSUE_TEMPLATES, $kind)))
        ->toBe(issueSkillSections($kind));
})->with(array_keys(ISSUE_TEMPLATES));

it('leaves scope to the acceptance criteria and outcomes, with no section of its own', function (
    string $path,
): void {
    expect(shippedSource($path))->not
        ->toMatch('/^#+ Out of scope/mi');
})->with(fn (): array => [...issueSkillFiles(), ...array_values(ISSUE_TEMPLATES)]);

it('gives each PBI template its one kind label, and a goal or epic none', function (
    string $path,
    string $label,
): void {
    expect(data_get(issueFrontMatter($path), 'labels'))->toBe($label);
})->with([
    'Product Goal' => [data_get(ISSUE_TEMPLATES, 'Product Goal'), ''],
    'Epic' => [data_get(ISSUE_TEMPLATES, 'Epic'), ''],
    'bug' => [data_get(ISSUE_TEMPLATES, '`bug`'), 'bug'],
    'enhancement' => [data_get(ISSUE_TEMPLATES, '`enhancement`'), 'enhancement'],
    'maintenance' => [data_get(ISSUE_TEMPLATES, '`maintenance`'), 'maintenance'],
    'research' => [data_get(ISSUE_TEMPLATES, '`research`'), 'research'],
]);

it('sets no issue type, which a personal repository does not offer', function (
    string $path,
): void {
    $frontMatter = issueFrontMatter($path);

    expect($frontMatter)->toHaveKeys(['name', 'about', 'title', 'labels']);
    expect($frontMatter)->not
        ->toHaveKey('type');
})->with(ISSUE_TEMPLATES);

it('names the skill and says when to use it', function (): void {
    $skill = issueFrontMatter(ISSUES_SKILL_FILE);

    expect(data_get($skill, 'name'))->toBe('github-issues');
    expect(data_get($skill, 'description'))->toContain(...SKILL_TRIGGERS);
});

it('links only to files inside the skill', function (): void {
    $links = collect(issueSkillFiles())
        ->flatMap(function (string $path): array {
            preg_match_all('/\]\(([^)#]+)\)/', shippedSource($path), $targets);

            return collect(data_get($targets, '1'))
                ->map(fn (string $target): string => implode('/', [dirname($path), $target]))
                ->all();
        });

    expect($links->all())->not
        ->toBe([]);
    expect($links->reject(fn (string $link): bool => is_file(REPOSITORY_ROOT . "/{$link}"))->all())
        ->toBe([]);
});

it('keeps its own writing rules and needs no outside tool', function (string $path): void {
    $found = collect(ISSUE_FORBIDDEN_TEXT)
        ->filter(fn (string $text): bool => str_contains(shippedSource($path), $text));

    expect($found->all())->toBe([]);
})->with(fn (): array => [...issueSkillFiles(), ...array_values(ISSUE_TEMPLATES)]);
