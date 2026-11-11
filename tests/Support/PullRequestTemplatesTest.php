<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\PullRequestTemplates;

const NO_TEMPLATE_FOUND = ['templates' => [], 'unreadable' => []];

beforeEach(function (): void {
    $this->project = makeTempDir();
});

afterEach(function (): void {
    removeTempDir($this->project);
});

it('finds no template in a project without one', function (): void {
    seedFiles($this->project, ['README.md' => "x\n", 'docs/guide.md' => "x\n"]);

    expect((new PullRequestTemplates)->inspect($this->project))->toBe(NO_TEMPLATE_FOUND);
});

it('finds a template at each place GitHub reads one, in any letter case', function (
    string $path,
): void {
    seedFiles($this->project, [$path => "Ours.\n"]);

    expect((new PullRequestTemplates)->inspect($this->project))
        ->toBe(['templates' => [$path], 'unreadable' => []]);
})->with([
    'the root' => 'pull_request_template.md',
    'docs' => 'docs/pull_request_template.md',
    '.github, upper case' => '.github/PULL_REQUEST_TEMPLATE.md',
    '.github, mixed case' => '.github/Pull_Request_Template.md',
    'the .github template folder' => '.github/PULL_REQUEST_TEMPLATE/feature.md',
    'the root template folder' => 'PULL_REQUEST_TEMPLATE/feature.md',
    'the docs template folder, lower case' => 'docs/pull_request_template/feature.md',
]);

it('lists every Markdown template in a template folder, and nothing else in it', function (): void {
    seedFiles($this->project, [
        '.github/PULL_REQUEST_TEMPLATE/bug.md' => "Bug.\n",
        '.github/PULL_REQUEST_TEMPLATE/feature.MD' => "Feature.\n",
        '.github/PULL_REQUEST_TEMPLATE/notes.txt' => "Notes.\n",
    ]);
    mkdir("{$this->project}/.github/PULL_REQUEST_TEMPLATE/nested.md");

    expect(data_get((new PullRequestTemplates)->inspect($this->project), 'templates'))->toBe([
        '.github/PULL_REQUEST_TEMPLATE/bug.md',
        '.github/PULL_REQUEST_TEMPLATE/feature.MD',
    ]);
});

it('ignores a template name in a folder GitHub does not read', function (): void {
    seedFiles($this->project, ['app/pull_request_template.md' => "x\n"]);

    expect((new PullRequestTemplates)->inspect($this->project))->toBe(NO_TEMPLATE_FOUND);
});

it('follows no symbolic link, so it never lists a file outside the project', function (): void {
    $outside = makeTempDir();
    seedFiles($outside, ['pull_request_template.md' => "x\n"]);
    mkdir("{$this->project}/.github");
    symlink($outside, "{$this->project}/docs");
    symlink("{$outside}/pull_request_template.md", "{$this->project}/pull_request_template.md");
    symlink($outside, "{$this->project}/.github/PULL_REQUEST_TEMPLATE");

    $found = (new PullRequestTemplates)->inspect($this->project);
    removeTempDir($outside);

    expect($found)->toBe(NO_TEMPLATE_FOUND);
});

it('names a folder it cannot list, with the cause', function (): void {
    seedFiles($this->project, ['docs/pull_request_template.md' => "x\n"]);
    chmod("{$this->project}/docs", MODE_UNREADABLE);

    $found = (new PullRequestTemplates)->inspect($this->project);
    chmod("{$this->project}/docs", TEMP_DIR_PERMISSIONS);

    expect(data_get($found, 'templates'))->toBe([]);
    expect(array_keys(data_get($found, 'unreadable')))->toBe(['docs']);
    expect(data_get($found, 'unreadable.docs'))
        ->toStartWith("Could not open {$this->project}/docs");
});
