<?php

declare(strict_types=1);

use Composer\IO\BufferIO;
use Illuminate\Support\Collection;
use MikeBronner\DevelopmentSettings\Plugin\Consent;
use MikeBronner\DevelopmentSettings\Plugin\PullRequestTemplateChoice;
use MikeBronner\DevelopmentSettings\Plugin\Tally;

const PULL_REQUEST_TEMPLATE = '.github/pull_request_template.md';

const PULL_REQUEST_HEADINGS = [
    'Related issues',
    'What changed',
    'Why',
    'QA steps',
    'Verified',
    'Before you approve',
];

const PULL_REQUEST_GUIDANCE = '/^>/';

const PULL_REQUEST_PLACEHOLDER = '/^(-|- Fixes #|1\.)$/';

const TEMPLATE_CREATED = [
    'pint.json' => '/package/pint.json',
    PULL_REQUEST_TEMPLATE => '/package/.github/pull_request_template.md',
];

const PULL_REQUEST_EXAMPLES = 'resources/boost/skills/github-issues/references/examples.md';

function markdownHeadings(string $markdown): array
{
    preg_match_all('/^## (.+)$/m', $markdown, $headings);

    return data_get($headings, '1');
}

function markdownLines(string $markdown): Collection
{
    return collect(explode("\n", $markdown))
        ->map(fn (string $line): string => trim($line))
        ->filter();
}

function isPullRequestGuidance(string $line): bool
{
    return (bool) preg_match(PULL_REQUEST_GUIDANCE, $line);
}

function pullRequestGuidance(): Collection
{
    return markdownLines(shippedSource(PULL_REQUEST_TEMPLATE))
        ->filter(fn (string $line): bool => isPullRequestGuidance($line))
        ->values();
}

function skillPullRequestSection(): string
{
    preg_match('/^## Pull requests\n(.*)\z/ms', shippedSource(ISSUES_SKILL_FILE), $section);

    return (string) data_get($section, '1');
}

function skillPullRequestSections(): array
{
    preg_match('/^### Sections\n(.*?)^### /ms', skillPullRequestSection(), $sections);
    preg_match_all('/^- \*\*(.+?)\*\*:/m', (string) data_get($sections, '1'), $names);

    return data_get($names, '1');
}

function examplePullRequestBody(): string
{
    preg_match(
            '/^## A pull request\n.*?^```markdown\n(.*?)^```$/ms',
            shippedSource(PULL_REQUEST_EXAMPLES),
            $body,
        );

    return (string) data_get($body, '1');
}

function pullRequestConsumer(): array
{
    $source = shippedSource(PULL_REQUEST_TEMPLATE);

    return makeConsumer([
        'manifest' => [PULL_REQUEST_TEMPLATE => [md5($source)]],
        'sources' => [PULL_REQUEST_TEMPLATE => $source],
        'paths' => ['files' => [PULL_REQUEST_TEMPLATE]],
    ]);
}

function isMarkdownHeading(string $line): bool
{
    return (bool) preg_match('/^## (.+)$/', $line);
}

function isPullRequestPlaceholder(string $line): bool
{
    return (bool) preg_match(PULL_REQUEST_PLACEHOLDER, $line);
}

function settledChoice(
    string $project,
    BufferIO $inputOutput,
    Closure $write,
    array $keys = [],
): Tally {
    $choice = new PullRequestTemplateChoice($project, $inputOutput);
    $choice->hold(TEMPLATE_CREATED);
    $tally = new Tally;
    $consent = new Consent($inputOutput);

    withKeyPresses($keys, fn () => $choice->settle($consent, $tally, $write));

    return $tally;
}

function projectHoldsShippedTemplate(string $project): bool
{
    $path = "{$project}/" . PULL_REQUEST_TEMPLATE;

    return is_file($path) && file_get_contents($path) === shippedSource(PULL_REQUEST_TEMPLATE);
}
