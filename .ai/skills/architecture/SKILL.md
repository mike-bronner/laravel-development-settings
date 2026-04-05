---
name: architecture
description: >
  Architecture review for Laravel monolith applications using DDD-lite organization. Use when evaluating
  application structure, planning domain boundaries, reviewing separation of concerns, assessing scalability,
  planning feature architecture, or making technology decisions within a Laravel monolith. Also trigger when
  discussing Actions vs Services, domain organization, or how to structure a new feature.
---

# Architect Reviewer — Laravel Monolith + DDD-Lite

When writing PHP code based on architectural decisions, also invoke the `laravel` skill for code style compliance.

Reviews and guides architecture decisions for Laravel monolith applications organized with domain-driven
design principles — not full DDD, but domain-aware organization with Actions, Concerns traits, and clear
boundaries.

## Expected Application Structure

```
app/
├── Actions/              # Single-purpose invokable classes
│   ├── Leads/
│   └── Billing/
├── Concerns/             # Shared traits organized by model
│   ├── Attributes/
│   │   └── Lead.php
│   └── Queries/
│       └── Lead.php
├── Http/
│   ├── Controllers/      # Thin, RESTful or invokable
│   ├── Requests/         # Form Request validation
│   └── Resources/        # API response shaping
├── Models/               # Lean models using Concerns traits
├── Policies/             # Authorization
├── Jobs/                 # Queue jobs
├── Events/               # Domain events
├── Listeners/            # Event handlers
└── Providers/            # Service providers
```

## Architecture Principles

### Actions Over Services
Use Action classes (single-purpose, invokable) instead of Service classes (multi-method, stateful):

```php
// Good — one purpose, clear name, invokable
class ProcessLeadAction
{
    public function __invoke(Lead $lead): ProcessedLead
    {
        // single responsibility
    }
}

// Avoid — multi-method service becomes a god class
class LeadService
{
    public function process() { }
    public function validate() { }
    public function normalize() { }
    public function export() { }
}
```

**Why Actions win**: they stay small, are easy to test, have clear names that describe what they do, and
can be composed. Services accumulate methods and become maintenance burdens.

**Note on `Action::run()`**: the `laravel` skill's "no statics" rule does not apply to Action classes
that use Laravel's Actions package (Loris Leiva). The `::run()` static method is an accepted convention
for invoking Actions — it delegates to `__invoke` internally.

### Domain Boundaries
Group related classes by what they do in the business domain, not by their Laravel type:

- All Lead-related Actions live in `Actions/Leads/`
- All Lead-related model traits live in `Concerns/Attributes/Lead.php` and `Concerns/Queries/Lead.php`
- All Lead-related events live in `Events/Lead*`

When a feature touches multiple domains (e.g., billing + leads), the orchestrating Action lives in the
primary domain and injects dependencies from the secondary.

### Controller Thinness
Controllers should have 1-5 lines per method. Business logic belongs in Actions or model methods:

```php
public function store(StoreLeadRequest $request): JsonResponse
{
    $lead = ProcessLeadAction::run(request: $request);

    return response()->json(data: new LeadResource(resource: $lead), status: 201);
}
```

## Architecture Review Checklist

When reviewing a feature or PR for architectural concerns:

### Structure
- [ ] New classes placed in correct domain directory
- [ ] Actions are single-purpose and invokable
- [ ] Controllers are thin (no business logic)
- [ ] Model logic extracted to Concerns traits
- [ ] No circular dependencies between domains

### Scalability
- [ ] Database queries are efficient (no N+1, proper indexes)
- [ ] Heavy work dispatched to queues, not handled synchronously
- [ ] Cache used for expensive computations or external API results
- [ ] File processing chunked for large datasets

### Coupling
- [ ] Dependencies injected via constructor, not hardcoded
- [ ] Interfaces used where implementations might change
- [ ] Events used for cross-domain communication (not direct calls)
- [ ] No direct model property access across domains (use attributes)

### Data Flow
- [ ] Request → Form Request → Controller → Action → Model → Resource → Response
- [ ] Validation happens in Form Requests, not controllers or models
- [ ] Data transformation happens in Resources, not controllers
- [ ] Persistence happens in model methods, not controllers or Actions

## When to Extract a Domain

Signs a domain boundary is forming:
- 5+ models that primarily relate to each other
- Actions that only touch one set of related models
- Events that only listeners within the same group care about
- A team member could own just this area of the codebase

## Anti-Patterns to Flag

- **God controllers** (>100 lines or >7 methods) → split into domain controllers
- **God models** (>250 lines) → extract Concerns traits
- **Service classes with >3 methods** → split into Actions
- **Direct DB queries in controllers** → move to model query traits
- **Business logic in Blade templates** → move to model attributes or computed properties
- **Cross-domain direct model access** → use events or inject via Action
