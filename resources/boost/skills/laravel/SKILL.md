---
name: laravel
description: >
  Solve Laravel development problems with enforced clean code patterns, PHP code style, and linter compliance.
  Use when writing or modifying ANY PHP or Blade file: fix N+1 Eloquent queries, create middleware, build Livewire
  components, refactor controllers, design models with relationships and scopes, write Pest tests, create migrations,
  write artisan commands, fix Pint linter errors, or implement any Laravel-specific pattern. This skill applies
  to ALL PHP and Blade changes in Laravel projects — even small edits. Also trigger when discussing Laravel
  architecture, Eloquent design, or PHP conventions used in this project.
---

# Laravel Specialist

Every line of PHP you write in a Laravel project must follow the clean code standards in this skill and pass the
project's linters. The general clean code standards ship as Boost guidelines from `mike-bronner/clean-code`.
The `references/` directory covers what those guidelines do not — consult the relevant file before writing
code in that domain.

## Use Laravel Boost MCP

Before writing code, use Laravel Boost MCP tools to understand the project context. Boost is always available
in Laravel projects that have this skill installed.

**Always use these Boost tools when relevant:**

- **`database-schema`** — before writing migrations, models, or queries. Understand existing tables, columns,
  indexes, and foreign keys so your code fits the actual schema.
- **`search-docs`** — when unsure about Laravel API, syntax, or best practices. Search Laravel and package
  docs for the exact version installed.
- **`application-info`** — to know PHP version, Laravel version, database engine, and installed packages.
- **`database-query`** — read-only queries to verify data assumptions before writing logic.
- **`last-error`** / **`read-log-entries`** — when debugging or writing error-handling code.

Routes, configuration and Artisan commands have no Boost tool, and Boost registers its `tinker` tool only
when `boost.tinker_tool_enabled` is on. Use the CLI:

- `php artisan route:list` before adding or modifying routes, to avoid conflicts and follow naming patterns.
- `php artisan config:show <key>` to check application configuration rather than guessing.
- `php artisan list` and `php artisan <command> --help` before creating or running Artisan commands.
- `php artisan tinker --execute '...'` to test snippets, verify model relationships, or check data before
  writing code that depends on runtime state.

Don't guess at schema, routes, or config — query Boost or Artisan and know.

## Reference Guides

Read the relevant reference file before writing code in that area:

| When writing...                        | Read                                 |
|----------------------------------------|--------------------------------------|
| Authorization policies                 | `references/policies.md`             |
| Pest syntax, datasets, patterns        | `references/pest-patterns.md`        |
| Debugging, error diagnosis             | `references/debugging.md`            |

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

### Classes

- **Properties are required.** Avoid classes that do not encapsulate any data. A class without
  properties has no state and no identity, and is the same as procedural, non-object-oriented code.

### Laravel-Specific Patterns

- **Controllers**: no business logic. RESTful or invokable only. Use Form Requests and Response classes.
- **Models**: no eager loading in `$with`. Use `with()` at query site. Extract attributes and queries to
  traits (`Concerns/Attributes/ModelName`, `Concerns/Queries/ModelName`). Descriptive persistence methods
  on the model (`$agent->addListingInfo($info)`), not generic CRUD. No separate repository classes.
- **Routes**: resource routes with RESTful controllers. No closures. Single model per route.
- **Livewire**: single root element, no Livewire/Blade/Alpine attributes on root. Unique `wire:key`.

## Eloquent Query Optimization

**N+1 detection**: always use `with()` at the query site, never the `$with` property:
```php
// Good — explicit eager loading
$leads = Lead::with(relations: ["agent", "lender"])->where(column: "status", operator: "=", value: "active")->get();

// Bad — lazy loading causes N+1
$leads = Lead::where(column: "status", operator: "=", value: "active")->get();
$leads->each(callback: fn ($lead) => $lead->agent->name); // N+1!
```

> ⚠️ **Named-argument footgun:** always pass `operator:` with `where()`. The two-argument shorthand `where(column: "x", value: "y")` — omitting `operator:` — silently compiles to `WHERE x IS NULL`, because Laravel's `func_num_args() === 2` heuristic treats the defaulted (null) `$operator` as the value. Always write `where(column: "x", operator: "=", value: "y")`.

**Chunking for large datasets**:
```php
Lead::query()
    ->where(column: "status", operator: "=", value: "pending")
    ->chunkById(count: 1_000, callback: function ($leads) {
        // Process batch
    });
```

**Subquery selects** to avoid loading full models:
```php
$leads = Lead::query()
    ->addSelect([
        "agent_name" => Agent::select(columns: "name")
            ->whereColumn(first: "agents.id", operator: "=", second: "leads.agent_id")
            ->limit(value: 1),
    ])
    ->get();
```

## Database Conventions

- **State every foreign key's on-delete behaviour, chosen per relationship.** There is no default, so
  never write a bare `->constrained()`:
  - `->restrictOnDelete()` when the child must outlive its parent: audit, PII/PHI and financial records.
  - `->cascadeOnDelete()` when the child is a pure child of the parent, such as a pivot row.
  - `->nullOnDelete()` when the child should survive without its parent. The column must be nullable.

  A database-level cascade fires no Eloquent events or observers, so the audit logging the `security`
  skill requires never sees a cascaded delete.

## Testing Conventions

Use **Pest** with expressive syntax. Structure tests with the **AAA pattern**, and mark each phase a test
has with an emoji section marker:

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
- Run tests with Test Impact Analysis when the project is on Pest 5: `vendor/bin/pest --tia --baselined`.
  The Pest Test Impact Analysis guideline (`06-pest-tia`) covers the baseline and its setup. Otherwise
  run them in parallel. A run with a test path or `--filter` never uses TIA, so run it in parallel.
- Give each of Arrange, Act and Assert its own section whenever that phase has content. A section
  with no content has no marker: a test with no separate setup has no Arrange marker.
- Never combine sections to remove a marker. Assertions go ONLY in the Assert section.
- Section markers override the "no comments" rule — they are the one exception.
- A marker is exactly `// 🧪 Arrange`, `// 🧪 Act` or `// 🧪 Assert`, alone on its line, with nothing
  after it. Never combine two phases in one marker: split them into separate markers. Put notes in
  prose, never after a marker.
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
# Pest 5: run only the tests your change affects
vendor/bin/pest --tia --baselined

# Without TIA, and for every filtered run
php artisan test --parallel
php artisan test --parallel --filter=TestName
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

1. Run `./vendor/bin/pint --test` on the PHP files you modified.
2. Review the linter output — each error includes a file path and line number.
3. Run `git diff` on the same files to see which lines you added or modified.
4. For each linter error, check whether its line number falls within a diff hunk.
5. **Error on a line you changed → fix it.** Read the rule name, understand the rule, fix manually.
6. **Error on a line outside the diff → leave it.** It's a pre-existing issue outside this PR's scope.
7. **Never run `./vendor/bin/pint` without `--test`** — auto-fix reformats entire files and creates
   noise unrelated to your changes.

### When Linter Rules Are Unclear

The linter configurations are the source of truth — not this skill file. If you encounter an unfamiliar
rule, read the actual config:

- **Pint rules**: read `pint.json` in the project root

These configs may change over time. Always defer to what the config files say over any memorized rules.
