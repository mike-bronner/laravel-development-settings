<?php

declare(strict_types=1);

const NEW_DEREFERENCE_PHP_VERSION_ID = 80_400;

afterEach(function (): void {
    isset($this->project) && removeTempDir($this->project);
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

it('instantiates a named class without empty parentheses', function (
    string $written,
    string $formatted,
): void {
    $this->project = pintFiles([PINT_SOURCE_FILE => "<?php\n\n{$written}\n"]);

    expect(file_get_contents("{$this->project}/" . PINT_SOURCE_FILE))
        ->toBe("<?php\n\n{$formatted}\n");
})->with([
    'empty parentheses' => ['$verse = new Verse();', '$verse = new Verse;'],
    'wrapped in parentheses' => ['$id = (new Verse())->id();', '$id = (new Verse)->id();'],
    'no parentheses, unchanged' => ['$verse = new Verse;', '$verse = new Verse;'],
    'arguments, unchanged' => ['$verse = new Verse(1);', '$verse = new Verse(1);'],
    'anonymous class, unchanged' => ['$verse = new class() {};', '$verse = new class() {};'],
]);

it('keeps the parentheses of a new object that is dereferenced', function (string $source): void {
    $source = "<?php\n\n{$source}\n";
    $this->project = pintFiles([PINT_SOURCE_FILE => $source]);

    expect(file_get_contents("{$this->project}/" . PINT_SOURCE_FILE))->toBe($source);
})->with([
    'object operator' => '$id = new Verse()->id();',
    'nullsafe operator' => '$id = new Verse()?->id();',
])->skip(
        PHP_VERSION_ID < NEW_DEREFERENCE_PHP_VERSION_ID,
        'Dereferencing a new object without wrapping parentheses needs PHP 8.4.',
    );

it('keeps a method chain that hangs from a multi-line call', function (): void {
    $source = file_get_contents(REPOSITORY_ROOT . '/tests/Fixtures/pint/hanging-chain.txt');
    $this->project = pintFiles([PINT_SOURCE_FILE => $source]);

    expect(file_get_contents("{$this->project}/" . PINT_SOURCE_FILE))->toBe($source);
});
