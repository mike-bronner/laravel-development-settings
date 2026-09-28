<?php

declare(strict_types=1);

use Composer\IO\BufferIO;
use Laravel\Prompts\Key;
use MikeBronner\DevelopmentSettings\Plugin\ContributionPrompt;

beforeEach(function (): void {
    [$this->project, $this->package] = makeConsumer([
        'sources' => [
            'resources/boost/guidelines/01-identity.md' => "Identity, fixed in this project\n",
            'resources/boost/guidelines/02-workflow.md' => "Workflow\n",
        ],
        'captured' => [
            'resources/boost/guidelines/01-identity.md' => [md5("Identity\n")],
            'resources/boost/guidelines/02-workflow.md' => [md5("Workflow\n")],
        ],
    ]);
    $this->output = new BufferIO();
});

afterEach(function (): void {
    removeTempDir($this->project);
});

it('names the edited sources and the contribute command, and opens nothing', function (): void {
    (new ContributionPrompt($this->output, $this->project, $this->package))->offer();

    $output = $this->output
        ->getOutput();

    expect($output)->toContain(
        '1 local edit(s) to shared development-settings files',
        'resources/boost/guidelines/01-identity.md',
        'vendor/bin/dev-settings-contribute.php',
    );
    expect($output)->not
        ->toContain('02-workflow.md');
    expect(file_get_contents("{$this->package}/resources/boost/guidelines/01-identity.md"))
        ->toBe("Identity, fixed in this project\n");
});

it('stays silent when no installed source was edited', function (): void {
    file_put_contents("{$this->package}/resources/boost/guidelines/01-identity.md", "Identity\n");

    (new ContributionPrompt($this->output, $this->project, $this->package))->offer();

    expect($this->output->getOutput())->toBe('');
});

it('opens nothing when the developer declines, which is the default', function (): void {
    $this->output
        ->setUserInputs([]);
    $prompt = new ContributionPrompt($this->output, $this->project, $this->package);

    withKeyPresses([Key::ENTER], fn () => $prompt->offer());

    expect($this->output->getOutput())
        ->toContain("run \"vendor/bin/dev-settings-contribute.php\" later to contribute.");
});
