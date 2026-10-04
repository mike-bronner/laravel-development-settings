<?php

declare(strict_types=1);

afterEach(function (): void {
    removeTempDir($this->project);
});

it('writes PHPUnit test method names in camel caps', function (
    string $written,
    string $formatted,
): void {
    $this->project = pintProject(methodName: $written);

    expect(file_get_contents("{$this->project}/" . PINT_TEST_FILE))
        ->toContain("public function {$formatted}()");
})->with([
    'camel case, unchanged' => ['returnsTheValue', 'returnsTheValue'],
    'snake case' => ['returns_the_value', 'returnsTheValue'],
    'snake case with the test prefix' => ['test_returns_the_value', 'testReturnsTheValue'],
]);

it('formats a test method name that the shipped phpcs.xml accepts', function (
    string $written,
): void {
    $this->project = pintProject(methodName: $written);

    $checked = collect(runPhpcs($this->project));

    expect($checked->keys())->toContain(PINT_TEST_FILE)
        ->and($checked->get(PINT_TEST_FILE))
        ->not
        ->toContain('PSR1.Methods.CamelCapsMethodName.NotCamelCaps');
})->with(['returnsTheValue', 'returns_the_value', 'test_returns_the_value']);

it('keeps an empty class-like body on its declaration line', function (string $declaration): void {
    $source = "<?php\n\n{$declaration} {}\n";
    $this->project = pintFiles([PINT_SOURCE_FILE => $source]);

    expect(file_get_contents("{$this->project}/" . PINT_SOURCE_FILE))->toBe($source);
})->with([
    'class' => 'final class TextualApparatusEntry extends BaseModel',
    'interface' => 'interface Annotated',
    'trait' => 'trait Annotates',
    'enum' => 'enum Testament',
]);

it('keeps a class body whose opening brace is on its own line', function (): void {
    $source = <<<PHP
        <?php

        final class Verse extends BaseModel
        {
            public int \$number = 1;
        }

        PHP;
    $this->project = pintFiles([PINT_SOURCE_FILE => $source]);

    expect(file_get_contents("{$this->project}/" . PINT_SOURCE_FILE))->toBe($source);
});

it('keeps a method chain that hangs from a multi-line call', function (): void {
    $source = file_get_contents(REPOSITORY_ROOT . '/tests/Fixtures/pint/hanging-chain.txt');
    $this->project = pintFiles([PINT_SOURCE_FILE => $source]);

    expect(file_get_contents("{$this->project}/" . PINT_SOURCE_FILE))->toBe($source);
});
