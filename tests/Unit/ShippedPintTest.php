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
