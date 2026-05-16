---
name: laravel
description: >
  Solve Laravel development problems with enforced clean code patterns, PHP code style, and linter compliance.
  Use when writing or modifying ANY PHP or Blade file: fix N+1 Eloquent queries, create middleware, build Livewire
  components, refactor controllers, design models with relationships and scopes, write Pest tests, create migrations,
  write artisan commands, fix phpcs/pint linter errors, or implement any Laravel-specific pattern. This skill applies
  to ALL PHP and Blade changes in Laravel projects — even small edits. Also trigger when discussing Laravel
  architecture, Eloquent design, or PHP conventions used in this project.
---

# Laravel Specialist

Every line of PHP you write in a Laravel project must follow the clean code standards in this skill and pass the
project's linters. The `references/` directory contains detailed rationale — consult the relevant file before
writing code in that domain.

## Use Laravel Boost MCP

Before writing code, use Laravel Boost MCP tools to understand the project context. Boost is always available
in Laravel projects that have this skill installed.

**Always use these Boost tools when relevant:**

- **`DatabaseSchema`** — before writing migrations, models, or queries. Understand existing tables, columns,
  indexes, and foreign keys so your code fits the actual schema.
- **`SearchDocs`** — when unsure about Laravel API, syntax, or best practices. Search Laravel and package
  docs for the exact version installed.
- **`ApplicationInfo`** — to know PHP version, Laravel version, installed packages, and available models.
- **`ListRoutes`** — before adding or modifying routes. See what exists to avoid conflicts and follow naming
  patterns.
- **`Tinker`** — to test snippets, verify model relationships, or check data before writing code that depends
  on runtime state.
- **`DatabaseQuery`** — read-only queries to verify data assumptions before writing logic.
- **`GetConfig`** / **`ListAvailableConfigKeys`** — to check application configuration rather than guessing.
- **`ListArtisanCommands`** — before creating or running artisan commands. See available commands and
  their parameters.
- **`LastError`** / **`ReadLogEntries`** — when debugging or writing error-handling code.

Don't guess at schema, routes, or config — query Boost and know.

## Reference Guides

Read the relevant reference file before writing code in that area. These explain the *why* behind patterns:

| When writing...                        | Read                                 |
|----------------------------------------|--------------------------------------|
| Any PHP code (first task per session)  | `references/governing-principles.md` |
| Conditionals, strings, arrays, types   | `references/code-cleanliness.md`     |
| Formatting, operators, indentation     | `references/code-style.md`           |
| Classes, interfaces, DI, constructors  | `references/classes.md`              |
| Eloquent models, traits, queries       | `references/models.md`              |
| Livewire components                    | `references/livewire.md`            |
| Controllers, form requests             | `references/controllers.md`         |
| Authorization policies                 | `references/policies.md`            |
| Test philosophy, TDD, suites           | `references/testing.md`             |
| Pest syntax, datasets, patterns        | `references/pest-patterns.md`       |
| Error handling, try/catch              | `references/exceptions.md`          |
| Route definitions                      | `references/routes.md`              |
| Debugging, error diagnosis             | `references/debugging.md`           |

## PHP Code Style

These rules apply to every line of PHP you write. They are the conventions most commonly violated by
AI-generated code and are stable across projects — they won't change with linter config updates.

### Absolute Rules

1. **`declare(strict_types=1);`** on every PHP file
2. **No `empty()`** — use `! $value` or explicit null checks
3. **No `else` / `elseif`** — early returns and guard clauses only
4. **Named arguments on ALL function/method calls** — `method(name: $value)`, never positional
5. **Space after negation** — `! $value` not `!$value`
6. **Double quotes for strings** — `"hello"` not `'hello'`
7. **Type hints on all parameters and return types**
8. **Constructor property promotion** — no separate property declarations
9. **`protected` over `private`** for methods and properties
10. **No comments or docblocks** unless explicitly requested. Exception: test section markers.
11. **Trailing commas** on multiline arrays, arguments, and parameters
12. **Strict comparison** (`===` / `!==`) — never loose (`==` / `!=`)
13. **`::class` syntax** for class references, never strings
14. **Alphabetically sorted** use statements, traits, and array keys
15. **`data_get()` for array access** — never use direct bracket access `$array["key"]`
16. **`Throwable` in catch blocks** — never catch `Exception`. Use `catch (Throwable)` (non-capturing)
    when the exception variable is unused.
17. **No static method calls** — use dependency injection or facades. Facades are not static calls
    (they proxy through the container). Exception: `Action::run()` from the Laravel Actions package.

### Golden Path Examples

A well-formed controller — no business logic, named arguments, strict types, Form Request:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Models\Book;
use Illuminate\Http\JsonResponse;

class BookController extends Controller
{
    public function store(StoreBookRequest $request): JsonResponse
    {
        $book = Book::createFromRequest(request: $request);

        return response()->json(data: $book, status: 201);
    }
}
```

A well-formed model attribute trait — new Attribute syntax, `data_get()`, no comments:

```php
<?php

declare(strict_types=1);

namespace App\Concerns\Attributes;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait Book
{
    protected function authorName(): Attribute
    {
        return Attribute::make(
            get: fn () => data_get(target: $this->author, key: "name", default: ""),
        );
    }
}
```

### Multi-Line Indentation (Project-Specific)

This project uses a custom indentation scheme that differs from PSR-12. Count from statement start (base):

| Location              | Indentation |
|-----------------------|-------------|
| Statement start       | Base        |
| Closing bracket       | Base + 1    |
| Chained methods       | Base + 1    |
| Closure/array content | Base + 2    |

```php
        $result = $items
            ->mapWithKeys(callback: function (Item $item): array {
                    return [$item->id => $item->name];
                })
            ->filter()
            ->toArray();
```

### Laravel-Specific Patterns

- **Controllers**: no business logic. RESTful or invokable only. Use Form Requests and Response classes.
- **Models**: no eager loading in `$with`. Use `with()` at query site. Extract attributes and queries to
  traits (`Concerns/Attributes/ModelName`, `Concerns/Queries/ModelName`). Descriptive persistence methods
  on the model (`$agent->addListingInfo($info)`), not generic CRUD. No separate repository classes.
  New application models should extend `App\Bases\Model` rather than `Illuminate\Database\Eloquent\Model`
  directly — `App\Bases\Model` provides `HasCamelCasing` (required for the project's camelCase attribute
  access convention) and `HasFactory`. For models that also need full-text search and soft deletes, extend
  `App\Bases\SearchableModel`. Exception: auth-related models such as `User` legitimately extend
  `Illuminate\Foundation\Auth\User` (`Authenticatable`) instead.
- **Routes**: resource routes with RESTful controllers. No closures. Single model per route.
- **Livewire**: single root element, no Livewire/Blade/Alpine attributes on root. Unique `wire:key`.

## Database Conventions

- **Foreign key cascades are the project default.** Most FK constraints use `->constrained()->cascadeOnDelete()->cascadeOnUpdate()`. Apply both modifiers when deleting a parent record should also remove the child. Use `->nullOnDelete()` instead of `->cascadeOnDelete()` when deleting a parent should orphan the child rather than remove it (e.g., `blog_posts.author_user_id`). Do not use bare `->constrained()` without explicit cascade or null-on-delete modifiers — the intent must always be stated.

## Testing Conventions

Use **Pest** with expressive syntax. All tests must follow the **AAA pattern** with emoji section markers:

```php
it("creates a new lead", function () {
    // 🧪 Arrange
    $payload = ["email" => "test@example.com"];

    // 🧪 Act
    $response = $this->postJson(uri: "/api/leads", data: $payload);

    // 🧪 Assert
    $response->assertStatus(status: 201);
});
```

**Rules:**
- All three sections required, even if minimal. Assertions ONLY in Assert section.
- Section markers override the "no comments" rule — they are the one exception.
- Use `describe()` to group related tests. Use datasets for multiple inputs.
- Mock external services you don't control. Never mock classes you control.
- Feature tests for HTTP endpoints. Unit tests for isolated logic. Integration tests for external services.
- `beforeEach()` for setup, `afterEach()` for cleanup. Setup > 10 lines → extract to helpers/traits.
- For Pest expectation API, datasets, edge case checklists, architecture tests, and Laravel-specific
  test patterns (HTTP, jobs, events, factories), see `references/pest-patterns.md`.
- **Advanced testing methods** — use where appropriate for the task:
  - **Browser testing** (Laravel Dusk) for critical user journeys and JavaScript-dependent flows
  - **Architecture tests** (`arch()`) to enforce structural rules (see `references/pest-patterns.md`)
  - **Stress testing** for endpoints handling high concurrency or large payloads
  - **Test coverage** (`--coverage --min=90`) to verify completeness
  - **Type coverage** (`--type-coverage --min=100`) to enforce strict typing across the codebase
  - **Mutation testing** (Infection) to verify test quality beyond line coverage
  - **Snapshot testing** (`toMatchSnapshot()`) for complex output structures (API responses, rendered views)

```bash
# Running tests
php artisan test --compact
php artisan test --compact --filter=TestName
```

## Related Skills

Invoke these sibling skills when your task crosses into their domain:

- **`postgres`** — when writing Eloquent queries, migrations, or optimizing query performance. Covers
  PostgreSQL-specific features (JSONB, CTEs, window functions, partial indexes) and execution plan analysis.
- **`security`** — when handling authentication, authorization, PII/PHI data, or reviewing code that
  touches sensitive fields. Covers HIPAA requirements, encryption, audit logging, and Laravel vulnerabilities.
- **`a11y`** — when writing or modifying Blade templates or Livewire components that users interact with.
  Covers WCAG 2.1 AA, ARIA patterns, keyboard navigation, and Livewire-specific accessibility concerns.
- **`refactor`** — when restructuring existing code, extracting Actions from controllers, breaking up
  models into Concerns traits, or reducing complexity. Covers safe refactoring workflow and code smells.
- **`architecture`** — when planning new features, evaluating domain boundaries, or making structural
  decisions about where code should live. Covers Actions vs Services, controller thinness, and DDD-lite.

## Lint Verification (Changed Lines Only)

After writing or modifying PHP files, verify your changes pass linters. **Only fix linter errors that
fall on lines you actually changed** — never fix pre-existing issues in untouched code.

### Workflow

1. Run `./vendor/bin/pint --test` and `./vendor/bin/phpcs` on the PHP files you modified.
2. Review the linter output — each error includes a file path and line number.
3. Run `git diff` on the same files to see which lines you added or modified.
4. For each linter error, check whether its line number falls within a diff hunk.
5. **Error on a line you changed → fix it.** Read the sniff name, understand the rule, fix manually.
6. **Error on a line outside the diff → leave it.** It's a pre-existing issue outside this PR's scope.
7. **Never run `./vendor/bin/pint` without `--test`** — auto-fix reformats entire files and creates
   noise unrelated to your changes.

### When Linter Rules Are Unclear

The linter configurations are the source of truth — not this skill file. If you encounter an unfamiliar
sniff or rule, read the actual config:

- **Pint rules**: read `pint.json` in the project root
- **PHPCS rules**: read `phpcs.xml` in the project root, then follow the `ref` to the custom ruleset
  (typically `.php-codesniffer/MikeBronner/ruleset.xml`)
- **PHPMD rules**: read `phpmd.xml` in the project root

These configs may change over time. Always defer to what the config files say over any memorized rules.
