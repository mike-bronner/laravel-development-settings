---
name: refactor
description: >
  Safe refactoring of Laravel PHP code following clean code principles. Use when reducing complexity in
  controllers, models, or services, extracting Actions from bloated classes, breaking up god models into
  Concerns traits, improving testability, or simplifying conditional logic. Also trigger when discussing
  code smells, technical debt reduction, or structural improvements in Laravel code.
---

# Refactoring Specialist — Laravel Clean Code

Systematic, test-driven refactoring for Laravel applications following the project's clean code conventions.
Always invoke the `laravel` skill before refactoring to ensure new code follows the project's
style rules.

## Refactoring Workflow

1. **Characterize**: write tests that capture current behavior (if tests don't exist)

```php
// Characterization test — captures current behavior before refactoring
it("captures current lead processing behavior", function () {
    // 🧪 Arrange
    $lead = Lead::factory()->create(attributes: [
        "email" => "test@example.com",
        "status" => "new",
    ]);

    // 🧪 Act
    $result = ProcessLeadAction::run(lead: $lead);

    // 🧪 Assert — document what currently happens, not what should happen
    expect($result)
        ->status->toBe("processed")
        ->score->toBeGreaterThan(0);
    expect($lead->fresh()->status)->toBe("processed");
});
```

2. **Identify**: name the specific smell or structural problem
3. **Plan**: choose the refactoring technique, verify it addresses the smell
4. **Execute**: small incremental changes, run tests after each change
5. **Verify**: all existing tests pass, new structure is cleaner
6. **Lint**: run pint and phpcs on changed files, fix only your lines

## Common Laravel Refactorings

### Extract Controller Logic to Action

**Smell**: controller method > 10 lines, contains business logic

```php
// Before — bloated controller
public function store(Request $request): JsonResponse
{
    $validated = $request->validate([...]);
    $lead = new Lead($validated);
    $lead->normalize();
    $lead->score = $this->calculateScore($lead);
    $lead->save();
    event(new LeadCreated($lead));

    return response()->json($lead, 201);
}

// After — thin controller + Action + Form Request
public function store(StoreLeadRequest $request): JsonResponse
{
    $lead = ProcessLeadAction::run(request: $request);

    return response()->json(data: new LeadResource(resource: $lead), status: 201);
}
```

### Extract Model Methods to Concerns Traits

**Smell**: model > 250 lines, mixes attributes + queries + business logic

Split into:
- `Concerns/Attributes/ModelName.php` — all accessor/mutator methods
- `Concerns/Queries/ModelName.php` — all query/scope methods
- Model file keeps: traits, properties, relationships

### Replace Conditionals with Early Returns

**Smell**: nested if/else, multiple conditions checking the same thing

```php
// Before
public function process(Lead $lead): Result
{
    if ($lead->isValid()) {
        if ($lead->hasAgent()) {
            if (! $lead->isProcessed()) {
                // actual logic 3 levels deep
            } else {
                return Result::alreadyProcessed();
            }
        } else {
            return Result::noAgent();
        }
    } else {
        return Result::invalid();
    }
}

// After — guard clauses, flat structure
public function process(Lead $lead): Result
{
    if (! $lead->isValid()) {
        return Result::invalid();
    }

    if (! $lead->hasAgent()) {
        return Result::noAgent();
    }

    if ($lead->isProcessed()) {
        return Result::alreadyProcessed();
    }

    // actual logic at top level
}
```

### Replace Multiple If-Statements with Mapping Array

**Smell**: sequential if-statements checking the same variable for different values

```php
// Before
if ($type === "monthly") { $multiplier = 1; }
if ($type === "weekly") { $multiplier = 4.33; }
if ($type === "biweekly") { $multiplier = 2.17; }

// After
$multipliers = [
    "biweekly" => 2.17,
    "monthly" => 1,
    "weekly" => 4.33,
];
$multiplier = data_get(target: $multipliers, key: $type, default: 1);
```

### Extract Eloquent Scopes to Query Trait

**Smell**: repeated `where` clauses across controllers/jobs

```php
// In Concerns/Queries/Lead.php
public function scopeActive(Builder $query): Builder
{
    return $query->where(column: "status", value: "active");
}

public function scopeForAgent(Builder $query, Agent $agent): Builder
{
    return $query->where(column: "agent_id", value: $agent->id);
}
```

## Code Smells to Watch For

| Smell | Threshold | Refactoring |
|-------|-----------|-------------|
| Long method | > 25 lines | Extract Method / Extract Action |
| Long class | > 250 lines | Extract Concerns traits |
| Deep nesting | > 2 levels | Replace with guard clauses |
| God controller | > 7 methods | Split into domain controllers |
| Feature envy | Method uses another model's data extensively | Move method to that model |
| Primitive obsession | Passing raw arrays/strings for structured data | Create Value Object |
| Duplicate logic | Same query/transform in 2+ places | Extract to model method or Action |

## Safety Rules

- **Never refactor without tests.** If tests don't exist, write characterization tests first.
- **One refactoring per commit.** Don't combine structural changes with behavior changes.
- **Run tests after every change.** If tests fail, revert the last change and try a smaller step.
- **Don't refactor and fix bugs simultaneously.** Refactor first, then fix the bug (or vice versa).
- **Preserve public interfaces.** Internal restructuring shouldn't change how callers use the class.
