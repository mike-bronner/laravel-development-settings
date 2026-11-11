<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

const ISSUES_SKILL = 'resources/boost/skills/github-issues';

const ISSUES_SKILL_FILE = ISSUES_SKILL . '/SKILL.md';

const ISSUE_TEMPLATES = [
    'Product Goal' => '.github/ISSUE_TEMPLATE/product-goal.md',
    'Epic' => '.github/ISSUE_TEMPLATE/epic.md',
    '`bug`' => '.github/ISSUE_TEMPLATE/bug.md',
    '`enhancement`' => '.github/ISSUE_TEMPLATE/enhancement.md',
    '`maintenance`' => '.github/ISSUE_TEMPLATE/maintenance.md',
    '`research`' => '.github/ISSUE_TEMPLATE/research.md',
];

const ISSUE_FRONT_MATTER = '/\A---\n(.*?)\n---\n(.*)\z/s';

function issueFrontMatter(string $path): array
{
    preg_match(ISSUE_FRONT_MATTER, shippedSource($path), $parts);

    return Yaml::parse(data_get($parts, '1'));
}

function issueTemplateHeadings(string $path): array
{
    preg_match(ISSUE_FRONT_MATTER, shippedSource($path), $parts);
    preg_match_all('/^## (.+)$/m', data_get($parts, '2'), $headings);

    return data_get($headings, '1');
}

function issueSkillSections(string $kind): array
{
    preg_match(
            '/^### Sections per kind\n(.*?)^###? /ms',
            shippedSource(ISSUES_SKILL_FILE),
            $section,
        );
    $row = collect(explode("\n", data_get($section, '1')))
        ->map(fn (string $line): Collection => issueTableCells($line))
        ->first(fn (Collection $cells): bool => in_array(
                $kind,
                explode(', ', $cells->first()),
                strict: true,
            ));

    return explode(', ', (string) preg_replace('/\s*\([^)]*\)/', '', $row->last()));
}

function issueTableCells(string $line): Collection
{
    return collect(explode('|', trim($line, '|')))
        ->map(fn (string $cell): string => trim($cell));
}

function issueSkillFiles(): array
{
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            REPOSITORY_ROOT . '/' . ISSUES_SKILL,
            FilesystemIterator::SKIP_DOTS,
        ));

    $root = REPOSITORY_ROOT . '/';

    return collect($files)
        ->map(fn (SplFileInfo $file): string => Str::after($file->getPathname(), $root))
        ->values()
        ->all();
}
