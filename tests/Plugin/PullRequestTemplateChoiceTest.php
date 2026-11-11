<?php

declare(strict_types=1);

use Composer\IO\BufferIO;
use Laravel\Prompts\Key;
use MikeBronner\DevelopmentSettings\Plugin\PullRequestTemplateChoice;
use MikeBronner\DevelopmentSettings\Plugin\Tally;
use MikeBronner\DevelopmentSettings\Support\PullRequestTemplates;

const BOTH_TEMPLATES = 2;

const OWN_TEMPLATE = 'docs/pull_request_template.md';

const OTHER_CREATED = ['pint.json' => '/package/pint.json'];

beforeEach(function (): void {
    $this->project = makeTempDir();
    $this->output = new BufferIO;
    $this->writes = [];
    $this->write = function (string $path, string $source): bool {
        $this->writes[$path] = $source;

        return true;
    };
    $this->writeFile = function (string $path, string $source): bool {
        seedFiles($this->project, [$path => "Shipped.\n"]);
        $this->writes[$path] = $source;

        return true;
    };
});

afterEach(function (): void {
    removeTempDir($this->project);
});

it('holds back nothing when the project has no template of its own', function (): void {
    $choice = new PullRequestTemplateChoice($this->project, $this->output);

    expect($choice->hold(TEMPLATE_CREATED))->toBe(TEMPLATE_CREATED);
    expect($choice->summaryLines())->toBe([]);
});

it('looks for no template when the shipped one is not about to be created', function (): void {
    seedFiles($this->project, [OWN_TEMPLATE => "Ours.\n"]);
    $choice = new PullRequestTemplateChoice($this->project, $this->output);

    expect($choice->hold(OTHER_CREATED))->toBe(OTHER_CREATED);
    expect($choice->summaryLines())->toBe([]);
});

it('holds back the shipped template when the project has its own', function (): void {
    seedFiles($this->project, [OWN_TEMPLATE => "Ours.\n"]);
    $choice = new PullRequestTemplateChoice($this->project, $this->output);

    expect($choice->hold(TEMPLATE_CREATED))->toBe(OTHER_CREATED);
    expect($choice->summaryLines())->toBe([['held', PullRequestTemplates::SHIPPED]]);
});

it('keeps the project template and says so in a run that cannot ask', function (): void {
    seedFiles($this->project, [OWN_TEMPLATE => "Ours.\n"]);

    $tally = settledChoice($this->project, $this->output, $this->write);

    expect($this->writes)->toBe([]);
    expect(file_get_contents("{$this->project}/" . OWN_TEMPLATE))->toBe("Ours.\n");
    expect($tally->count(Tally::SKIPPED))->toBe(1);
    expect($this->output->getOutput())->toContain(
            PullRequestTemplates::SHIPPED . ' was not written, because the project has its own'
                . ' pull request template: ' . OWN_TEMPLATE . '.',
        );
});

it('keeps the project template when the developer accepts the default', function (): void {
    seedFiles($this->project, [OWN_TEMPLATE => "Ours.\n"]);
    $interactive = new BufferIO;
    $interactive->setUserInputs([]);

    $tally = settledChoice($this->project, $interactive, $this->write, [Key::ENTER]);

    expect($this->writes)->toBe([]);
    expect($interactive->getOutput())->not
        ->toContain('was not written');
    expect(file_get_contents("{$this->project}/" . OWN_TEMPLATE))->toBe("Ours.\n");
    expect($tally->count(Tally::SKIPPED))->toBe(1);
});

it('writes the shipped template and deletes every project template on replace', function (): void {
    $folderTemplate = '.github/PULL_REQUEST_TEMPLATE/feature.md';
    seedFiles($this->project, [OWN_TEMPLATE => "Ours.\n", $folderTemplate => "Feature.\n"]);
    $interactive = new BufferIO;
    $interactive->setUserInputs([]);

    $tally = settledChoice($this->project, $interactive, $this->write, [Key::DOWN, Key::ENTER]);

    expect(array_keys($this->writes))->toBe([PullRequestTemplates::SHIPPED]);
    expect("{$this->project}/" . OWN_TEMPLATE)->not
        ->toBeFile();
    expect("{$this->project}/{$folderTemplate}")->not
        ->toBeFile();
    expect($tally->count(Tally::NEW))->toBe(1);
    expect($tally->count(Tally::REMOVED))->toBe(BOTH_TEMPLATES);
});

it('deletes no project template when the shipped one fails to write', function (): void {
    seedFiles($this->project, [OWN_TEMPLATE => "Ours.\n"]);
    $interactive = new BufferIO;
    $interactive->setUserInputs([]);
    $this->write = fn (): bool => false;

    $tally = settledChoice($this->project, $interactive, $this->write, [Key::DOWN, Key::ENTER]);

    expect(file_get_contents("{$this->project}/" . OWN_TEMPLATE))->toBe("Ours.\n");
    expect($tally->count(Tally::SKIPPED))->toBe(1);
    expect($tally->count(Tally::REMOVED))->toBe(0);
});

it('takes the shipped template back out when a project template is not deleted', function (): void {
    seedFiles($this->project, [OWN_TEMPLATE => "Ours.\n"]);
    chmod("{$this->project}/docs", MODE_LOCKED_DIRECTORY);
    $interactive = new BufferIO;
    $interactive->setUserInputs([]);

    $replace = [Key::DOWN, Key::ENTER];
    $tally = settledChoice($this->project, $interactive, $this->writeFile, $replace);
    chmod("{$this->project}/docs", TEMP_DIR_PERMISSIONS);

    expect("{$this->project}/" . OWN_TEMPLATE)->toBeFile();
    expect("{$this->project}/" . PullRequestTemplates::SHIPPED)->not
        ->toBeFile();
    expect($tally->count(Tally::NEW))->toBe(0);
    expect($tally->count(Tally::SKIPPED))->toBe(1);
    expect($tally->count(Tally::REMOVED))->toBe(0);
    expect($interactive->getOutput())->toContain(
            OWN_TEMPLATE . ' was not deleted. Could not delete ',
            'Permission denied',
            PullRequestTemplates::SHIPPED . ' was taken back out, so the project keeps its own'
                . ' pull request template. The next interactive run asks again.',
        );
});

it('asks again on the next interactive run after a replace was taken back', function (): void {
    seedFiles($this->project, [OWN_TEMPLATE => "Ours.\n"]);
    chmod("{$this->project}/docs", MODE_LOCKED_DIRECTORY);
    $interactive = new BufferIO;
    $interactive->setUserInputs([]);
    settledChoice($this->project, $interactive, $this->writeFile, [Key::DOWN, Key::ENTER]);
    chmod("{$this->project}/docs", TEMP_DIR_PERMISSIONS);

    $tally = settledChoice($this->project, $interactive, $this->writeFile, [Key::DOWN, Key::ENTER]);

    expect("{$this->project}/" . OWN_TEMPLATE)->not
        ->toBeFile();
    expect("{$this->project}/" . PullRequestTemplates::SHIPPED)->toBeFile();
    expect($tally->count(Tally::NEW))->toBe(1);
    expect($tally->count(Tally::REMOVED))->toBe(1);
});

it('names the template to delete by hand when the shipped one stays', function (): void {
    seedFiles($this->project, [OWN_TEMPLATE => "Ours.\n"]);
    chmod("{$this->project}/docs", MODE_LOCKED_DIRECTORY);
    $interactive = new BufferIO;
    $interactive->setUserInputs([]);
    $write = function (string $path, string $source): bool {
        ($this->writeFile)($path, $source);
        chmod(dirname("{$this->project}/{$path}"), MODE_LOCKED_DIRECTORY);

        return true;
    };

    $tally = settledChoice($this->project, $interactive, $write, [Key::DOWN, Key::ENTER]);
    chmod("{$this->project}/docs", TEMP_DIR_PERMISSIONS);
    chmod("{$this->project}/.github", TEMP_DIR_PERMISSIONS);

    expect("{$this->project}/" . OWN_TEMPLATE)->toBeFile();
    expect("{$this->project}/" . PullRequestTemplates::SHIPPED)->toBeFile();
    expect($tally->count(Tally::NEW))->toBe(1);
    expect($tally->count(Tally::SKIPPED))->toBe(0);
    expect($interactive->getOutput())->toContain(
            OWN_TEMPLATE . ' was not deleted. Could not delete ',
            PullRequestTemplates::SHIPPED . ' could not be taken back out. Could not delete ',
            "The shipped template and the project's own template now both exist."
                . ' Delete ' . PullRequestTemplates::SHIPPED . ' by hand.',
        );
});

it('asks nothing and writes nothing when a folder could not be searched', function (): void {
    seedFiles($this->project, ['docs/notes.md' => "x\n"]);
    chmod("{$this->project}/docs", MODE_UNREADABLE);
    $interactive = new BufferIO;
    $interactive->setUserInputs([]);

    $tally = settledChoice($this->project, $interactive, $this->write);
    chmod("{$this->project}/docs", TEMP_DIR_PERMISSIONS);

    expect($this->writes)->toBe([]);
    expect($tally->count(Tally::SKIPPED))->toBe(1);
    expect($interactive->getOutput())->toContain(
            PullRequestTemplates::SHIPPED . ' was not written, because a folder could not be'
                . " searched for the project's own pull request template. Could not open ",
        );
});

it('says in the summary that a folder held the template back, not a template', function (): void {
    seedFiles($this->project, ['docs/notes.md' => "x\n"]);
    chmod("{$this->project}/docs", MODE_UNREADABLE);
    $choice = new PullRequestTemplateChoice($this->project, $this->output);

    $kept = $choice->hold(TEMPLATE_CREATED);
    chmod("{$this->project}/docs", TEMP_DIR_PERMISSIONS);

    expect($kept)->toBe(OTHER_CREATED);
    expect($choice->summaryLines())->toBe([['unsearched', PullRequestTemplates::SHIPPED]]);
});
