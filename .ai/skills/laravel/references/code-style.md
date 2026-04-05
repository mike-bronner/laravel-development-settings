# Code Style

## Industry Standards

All code style must adhere to the following PHP standards:

- PSR1
- PSR2
- PSR12

## Linters

Your editor must support PHPCS linting, and be configured to use the `phpcs.xml` file in the root of the project. Your editor will then alert you to any style violation that we have linters for (which won't be all). Violations not covered by linters should be attempted to be caught and fixed during review.

We should not use any sort of auto-formatter that corrects linter issues, as this blows out the reviews and hides the actual changes made. Further, manual correction reinforces good coding habits, and after a short time you will be able to write mostly clean code with ease.

## Strings

### Multiline Strings

All multiline strings should use HEREDOC syntax:

```php
$string = <<<HTML
    <div>
        Hello, world!
    </div>
HTML;
```

This is especially important for inline SQL statements:

```php
$sql = DB::statement(<<<SQL
    SELECT "Hello, world!"
SQL);
```

## Operators

### Evaluative

These operators should never have a new line to the right or left:

```php
if ("hello" === "world") {
    //
}
```

- Comparison: `==`, `===`, `!=`, `<>`, `!==`, `<`, `>`, `<=`, `>=`, `<=>`
- Type: `instanceof`

### Manipulative

Manipulation operators should start on a new line in standalone statements, but may be written in a single line if acting as parameters:

```php
$result = 4
    + 4;
$result = floor(4 + 4.1);
$string = "Hello"
    . Str::lower(", world!");

if (
    $isTrue
    && $isAlsoTrue
) {
    //
}
```

The following are the most common manipulation operators:

- Strings: `.`
- Math: `+`, `-`, `/`, `*`, `%`, `**`
- Logical: `&&`, `||`
- Bitwise: `&`, `|`, `^`, `~`, `<<`, `>>`

### Active

These operators should have a space between themselves and the object they are acting on:

- Arithmetic assignment: `=`, `+=`, `-=`, `/=`, `*=`, `%=`, `**=`
- Bitwise assignment: `&=`, `|=`, `^=`, `<<=`, `>>=`
- Other assignment: `.=`, `??=`
- Logical: `and`, `or`, `xor`, `!`, `&&`, `||`
- String: `.`

### Passive

These operators should have no space between themselves and the object they are acting on:

- Identity: `+`
- Negation: `-`
- Increment: `++`
- Decrement: `--`
- Error control: `@`
- Execution: `` ` ``
- Access: `[]`, `->`
