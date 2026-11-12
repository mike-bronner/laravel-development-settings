<?php

declare(strict_types=1);

use Laravel\Prompts\Key;

const PULL_REQUEST_CHECKBOX = '/^\s*- \[[ x]\]/mi';

const PROJECT_OWN_TEMPLATE = "Our own template.\n";

const PROJECT_TEMPLATE_PLACES = [
    'the root' => 'pull_request_template.md',
    'docs' => 'docs/pull_request_template.md',
    'the template folder' => '.github/PULL_REQUEST_TEMPLATE/feature.md',
];

const PULL_REQUEST_SKILL_RULES = [
    'about 250 words',
    'ticks each criterion in the issue',
    'never copies or restates them',
    'closes the issue only when the pull request merges',
    'into the default branch',
    'On any other base branch, GitHub ignores the keyword. It creates no link,',
    'and the merge leaves the issue open.',
    'GitHub needs the full syntax for each issue that it closes.',
    'the reason for the change when the linked issue gives none',
    'It never repeats the reasoning that the issue already gives.',
    'reasoning that the linked issue already gives',
    'rejected alternatives or red-then-green transcripts. They go in the commit',
    'Read the issue body again just before each edit, never from an earlier copy.',
    'close, closes, closed, fix, fixes, fixed, resolve, resolves or resolved',
    'Use `Fixes` for a bug. Use `Closes` or `Resolves` for other work.',
    'Give each issue its own keyword.',
    '`Part of #<number>` or `Related to #<number>`',
    'departs from the issue, from the project\'s usual patterns or from a stated requirement',
    'a numbered playbook that a human follows in the browser or the console',
    'The QA steps do not count toward it',
    'It holds only automated evidence.',
    'Read the repository\'s pull request template',
    'always pass it with `--body-file`',
    'Never request a reviewer',
];

afterEach(function (): void {
    isset($this->project) && removeTempDir($this->project);
});

it('ships the pull request template to every project as a tracked file', function (): void {
    $checksum = md5(shippedSource(PULL_REQUEST_TEMPLATE));

    expect(shippedFiles())->toHaveKey(PULL_REQUEST_TEMPLATE, PULL_REQUEST_TEMPLATE);
    expect(shippedManifest()->isKnown(PULL_REQUEST_TEMPLATE, $checksum))->toBeTrue();
});

it('writes the pull request template into a consuming project on an update', function (): void {
    [$this->project] = pullRequestConsumer();

    $output = publishIn($this->project);

    expect(file_get_contents("{$this->project}/" . PULL_REQUEST_TEMPLATE))
        ->toBe(shippedSource(PULL_REQUEST_TEMPLATE));
    expect($output)->toContain(PULL_REQUEST_TEMPLATE);
});

it('leaves a project\'s own pull request template in place as locally modified', function (): void {
    [$this->project] = pullRequestConsumer();
    seedFiles($this->project, [PULL_REQUEST_TEMPLATE => PROJECT_OWN_TEMPLATE]);

    $output = publishIn($this->project);

    expect(file_get_contents("{$this->project}/" . PULL_REQUEST_TEMPLATE))
        ->toBe(PROJECT_OWN_TEMPLATE);
    expect($output)->toContain(PULL_REQUEST_TEMPLATE . ' (locally modified)');
});

it('keeps a project template found elsewhere, and writes none, in a run that cannot ask', function (
    string $own,
): void {
    [$this->project] = pullRequestConsumer();
    seedFiles($this->project, [$own => PROJECT_OWN_TEMPLATE]);

    $output = publishIn($this->project);

    expect(file_get_contents("{$this->project}/{$own}"))->toBe(PROJECT_OWN_TEMPLATE);
    expect(projectHoldsShippedTemplate($this->project))->toBeFalse();
    expect($output)->toContain(
            PULL_REQUEST_TEMPLATE . ' (held back for a project template)',
            PULL_REQUEST_TEMPLATE . ' was not written, because the project has its own pull request'
                . " template: {$own}.",
            '1 skipped',
        );
})->with(PROJECT_TEMPLATE_PLACES);

it('asks before it replaces a project template found elsewhere, and keeps it by default', function (
    string $own,
): void {
    [$this->project] = pullRequestConsumer();
    seedFiles($this->project, [$own => PROJECT_OWN_TEMPLATE]);

    $output = withKeyPresses([Key::ENTER], fn (): string => publishIn($this->project, INTERACTIVE));

    expect(file_get_contents("{$this->project}/{$own}"))->toBe(PROJECT_OWN_TEMPLATE);
    expect(projectHoldsShippedTemplate($this->project))->toBeFalse();
    expect($output)->toContain('1 skipped');
})->with(PROJECT_TEMPLATE_PLACES);

it('replaces a project template found elsewhere when the developer picks it', function (
    string $own,
): void {
    [$this->project] = pullRequestConsumer();
    seedFiles($this->project, [$own => PROJECT_OWN_TEMPLATE]);

    $output = withKeyPresses(
            [Key::DOWN, Key::ENTER],
            fn (): string => publishIn($this->project, INTERACTIVE),
        );

    expect("{$this->project}/{$own}")->not
        ->toBeFile();
    expect(projectHoldsShippedTemplate($this->project))->toBeTrue();
    expect($output)->toContain('0 skipped', '1 removed');
})->with(PROJECT_TEMPLATE_PLACES);

it('treats an upper-case .github template by how the filesystem reads case', function (): void {
    [$this->project] = pullRequestConsumer();
    $own = '.github/PULL_REQUEST_TEMPLATE.md';
    seedFiles($this->project, [$own => PROJECT_OWN_TEMPLATE]);
    $isSameFile = file_exists("{$this->project}/" . PULL_REQUEST_TEMPLATE);

    $output = publishIn($this->project);

    expect(file_get_contents("{$this->project}/{$own}"))->toBe(PROJECT_OWN_TEMPLATE);
    expect($output)->toContain(match ($isSameFile) {
        true => PULL_REQUEST_TEMPLATE . ' (locally modified)',
        false => PULL_REQUEST_TEMPLATE . ' (held back for a project template)',
    });
});

it('states only the keyword rules GitHub documents', function (string $path): void {
    expect(shippedSource($path))->not
        ->toContain('#12, #13')
        ->not
        ->toContain('only references');
})->with([PULL_REQUEST_TEMPLATE, ISSUES_SKILL_FILE]);

it('holds exactly the six pull request sections, in order', function (): void {
    expect(markdownHeadings(shippedSource(PULL_REQUEST_TEMPLATE)))->toBe(PULL_REQUEST_HEADINGS);
});

it('holds no provenance, acceptance criteria or CI checklist', function (): void {
    $template = shippedSource(PULL_REQUEST_TEMPLATE);

    expect($template)->not
        ->toMatch('/^#+ .*(Provenance|Acceptance|Checklist)/mi');
    expect($template)->not
        ->toMatch(PULL_REQUEST_CHECKBOX);
});

it('tells whoever fills it to tick each met criterion in the linked issue', function (): void {
    expect(pullRequestGuidance()->implode("\n"))
        ->toContain('Tick each acceptance criterion in that issue as it is met.')
        ->toContain('Never copy the criteria here.');
});

it('names every GitHub closing keyword and when each closes an issue', function (): void {
    expect(pullRequestGuidance()->implode("\n"))
        ->toContain('close, closes, closed, fix, fixes, fixed, resolve, resolves or resolved')
        ->toContain('Letter case does not matter.')
        ->toContain('Use `Fixes` for a bug. Use `Closes` or `Resolves` for other work.')
        ->toContain('Give each issue its own keyword.')
        ->toContain('only when the pull request merges into the default branch')
        ->toContain('GitHub ignores the keyword. It creates no link,')
        ->toContain('and the merge leaves the issue open.')
        ->toContain('GitHub needs the full syntax for each issue that it closes.')
        ->toContain('Close the issue by hand')
        ->toContain('`Part of #<number>` or `Related to #<number>`');
});

it('asks Why for a missing reason and for each divergent choice, and no more', function (): void {
    expect(pullRequestGuidance()->implode("\n"))
        ->toContain('Give the reason for the change when the linked issue gives none.')
        ->toContain('Give a short reason for each choice that departs from the issue,')
        ->toContain('from the project\'s usual patterns or from a stated requirement.')
        ->toContain('Leave out the reasoning that the issue already gives.')
        ->toContain('Put rejected alternatives and red-then-green transcripts in the commit')
        ->toContain('transcripts in the commit messages.');
});

it('asks for a numbered QA playbook and keeps manual steps out of Verified', function (): void {
    $guidance = pullRequestGuidance()->implode("\n");

    expect($guidance)
        ->toContain('Write a numbered playbook that a human follows in the browser or the console.')
        ->toContain('Give each step one action and what the human should see after it.')
        ->toContain('Give the test command and its pass count, and the CI result.')
        ->toContain('Put manual steps in QA steps.');
    expect(markdownLines(shippedSource(PULL_REQUEST_TEMPLATE))->all())->toContain('1.');
});

it('marks every guidance line, so deleting them leaves none', function (): void {
    $unmarked = markdownLines(shippedSource(PULL_REQUEST_TEMPLATE))
        ->reject(fn (string $line): bool => isMarkdownHeading($line))
        ->reject(fn (string $line): bool => isPullRequestGuidance($line))
        ->reject(fn (string $line): bool => isPullRequestPlaceholder($line));

    expect(pullRequestGuidance()->first())
        ->toContain('Delete the guidance lines of this template as you fill it.')
        ->toContain('A quote that you add yourself stays.');
    expect($unmarked->all())->toBe([]);
});

it('shows a filled body that keeps no template guidance', function (): void {
    $body = examplePullRequestBody();
    $leftOver = pullRequestGuidance()
        ->filter(fn (string $line): bool => str_contains($body, $line));
    $marked = markdownLines($body)
        ->filter(fn (string $line): bool => isPullRequestGuidance($line));

    expect($body)->toMatch('/^- Fixes #\d+$/m');
    expect($body)->toMatch('/^- (Part of|Related to) #\d+$/m');
    expect($marked->all())->toBe([]);
    expect($leftOver->all())->toBe([]);
    expect($body)->not
        ->toMatch(PULL_REQUEST_CHECKBOX);
});

it('fills the template sections in the template\'s order', function (): void {
    $headings = markdownHeadings(examplePullRequestBody());

    expect($headings)->not
        ->toBe([]);
    expect(array_values(array_intersect(PULL_REQUEST_HEADINGS, $headings)))->toBe($headings);
});

it('gives the skill the sections the template holds', function (): void {
    expect(skillPullRequestSections())->toBe(PULL_REQUEST_HEADINGS);
});

it('gives the skill each pull request rule', function (string $rule): void {
    expect(preg_replace('/\s+/', ' ', skillPullRequestSection()))->toContain($rule);
})->with(PULL_REQUEST_SKILL_RULES);
