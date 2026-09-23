# Code Cleanliness

## Conditionals

### No Inline If-Statements

- Do not use inline if-statements.

**Why:** Makes code easier to parse (reduces mental debt).

### Avoid Conditionals

Avoid conditionals where possible.

**Why:** They increase cyclomatic complexity, which increases mental debt.

### No `else` or `elseif`

If you have to use if-statements in PHP, never use `else` or `elseif`.

**Why:**

- They are unnecessary lines of code that can be achieved with fewer lines of code in simpler terms (reduces mental debt).
- Reduces code complexity (reduces mental debt).

### One Condition Per Line

- If there is only a single condition in an if-statement, keep the if-statement condition portion on a single line, do not place the condition on its own line.
- For conditions that consist of multiple conditions, place each condition on its own line with the operator preceding the condition.

**Why:** Makes code easier to parse (reduces mental debt).

### Ternary Conditionals

- Use ternary operators instead of if statements where possible.
- Do not nest ternary conditions, instead assign to variables or refactor to methods.

**Why:** Makes code easier to parse (reduces mental debt).

### Combine Where Possible

- Combine sequential conditions that have the same result.

**Why:** Reduces code complexity (reduces mental debt).

### Mapping Arrays

- Use mapping arrays instead of multiple if-statements when inspecting different values of the same variable.

**Why:** Reduces code complexity (reduces mental debt).

## Strings

- Use interpolation in favor of concatenation.
- HTML attributes should always use quotes, never apostrophes.
- Defining HTML or other code to be rendered within code should be done using HereDocs.
- Escape quotes when rendering inside other quotes.

**Why:**

- Strings quoted in the same way can be sorted.
- Reduced mental load when reading code that has nested quotes.
- Adherence to HTML5 standards.

## Collections

### Only Use Collection Methods

- Don't use generic PHP methods on collections, collections should be implemented for the built-in methods, as they are optimized, and decouple us from direct PHP implementation.

**Why:**

- Decoupling from PHP methods (technical debt).
- Optimized functionality (performance).
- Consistent usage (mental debt).

## Arrays

### Convert To Collection

- Whenever possible use collections for manipulation.

**Why:**

- Collections provide a large list of optimized manipulation methods.
- By relying on the framework, we are decoupling from the direct PHP implementation, which may see changes over major versions, while the framework will maintain optimized implementation.

### Array Accessors

Always use `data_get()` to access arrays, instead of accessing their elements directly.

**Why:**

- This provides fallback logic in case the element does not exist in arrays.
- Allows parsing of properties on any kind of object (array, collection, object, model), thus not requiring type checks (reduces mental debt).
- Allows easy parsing of nested objects (reduces mental debt).

## Operators

See `code-style.md` for the full operator taxonomy (evaluative, manipulative, active, passive) and spacing rules.

## Indentation

### Methods

Nesting code often indicates different concepts or concerns, and are indication that code should be refactored. Methods should have no more than 2 levels of nesting.

**Why:**

- Reduces code complexity (reduces mental debt).
- Separation of concerns (group code by concepts).

### Multi-Line Statements

If a single statement extends over multiple lines, any lines subsequent to the first should be indented by one level.

**Why:** Indicates coherence between lines of code (mental debt).

### Logical Groupings

If-statements that have complex logic should have one condition per line, and groupings of conditions (using parentheses) should indent subsequent conditions to the first within the parentheses.

**Why:** Indicates the logical structure of conditions, so they can be quickly parsed (mental debt).

## Blank Lines

- Should only be used to separate concepts.
- At most there should be a single blank line, never multiple.
- There should be no blank lines at the beginning or end of classes, methods, or functions.

**Why:** Blank lines have meaning, and should only be used where appropriate to provide consistency and improve parsing of code (mental debt, clear code).

## Line Length

Lines of code should be no longer than 100 characters, and may absolutely be no longer than 120 characters.

**Why:** Makes code easier to parse (reduces mental debt, one thought per line).

## Type Hints and Return Types

- Type hint all method parameters and return values.
- Type hints serve as documentation, making the code more fluent and readable.
- Type hints and return types prevent some logic errors from propagating, catching them as close as possible to their source.

**Why:**

- Catches logic issues related to unexpected data changes close to the source.
- Makes code easier to understand (reduces mental debt).
