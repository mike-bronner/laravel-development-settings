# Pest Patterns & Syntax Reference

Practical Pest 4 patterns for Laravel projects. For testing philosophy (when to mock, TDD workflow,
test suite types), see `testing.md`.

## Table of Contents
- [Expectation API](#expectation-api)
- [Datasets](#datasets) (inline, named, combining)
- [Edge Case Checklist](#edge-case-checklist)
- [Negative Path Testing](#negative-path-testing)
- [Laravel HTTP Tests](#laravel-http-tests) (validation, jobs, events, factories)
- [Architecture Tests](#architecture-tests)
- [Test Organization](#test-organization)
- [Grouping with Describe](#grouping-with-describe)
- [Test Strategy & Quality Metrics](#test-strategy--quality-metrics) (risk planning, CI/CD, flaky tests)

## Expectation API

```php
expect($value)->toBe("exact");
expect($value)->toEqual(["loose", "comparison"]);
expect($value)->toBeTrue();
expect($value)->toBeFalse();
expect($value)->toBeNull();
expect($value)->toBeEmpty();
expect($value)->toBeInstanceOf(SomeClass::class);
expect($value)->toContain("substring");
expect($value)->toHaveCount(3);
expect($value)->toHaveKey("key");
expect($value)->toMatchArray(["partial" => "match"]);
```

### Higher-Order Expectations

```php
expect($user)
    ->name->toBe("John")
    ->email->toEndWith("@example.com")
    ->isAdmin->toBeFalse();
```

### Exception Testing

```php
it("throws on invalid input", function () {
    // 🧪 Arrange
    $processor = new DataProcessor();

    // 🧪 Act & Assert
    expect(fn () => $processor->process(data: null))
        ->toThrow(InvalidArgumentException::class, "Data cannot be null");
});
```

## Datasets

### Inline Datasets

```php
it("validates email formats", function (string $email, bool $expected) {
    // 🧪 Arrange
    $validator = new EmailValidator();

    // 🧪 Act
    $result = $validator->isValid(email: $email);

    // 🧪 Assert
    expect($result)->toBe($expected);
})->with([
    "valid standard email" => ["user@example.com", true],
    "valid with subdomain" => ["user@mail.example.com", true],
    "missing @ symbol" => ["userexample.com", false],
    "missing domain" => ["user@", false],
    "empty string" => ["", false],
]);
```

### Named Datasets

```php
dataset("invalid_ssn_formats", [
    "too short" => ["123-45-678"],
    "too long" => ["123-45-67890"],
    "letters included" => ["123-AB-6789"],
    "wrong separators" => ["123.45.6789"],
    "all zeros" => ["000-00-0000"],
]);

it("rejects invalid SSN formats", function (string $ssn) {
    // 🧪 Arrange
    $validator = new SsnValidator();

    // 🧪 Act
    $result = $validator->isValid(ssn: $ssn);

    // 🧪 Assert
    expect($result)->toBeFalse();
})->with("invalid_ssn_formats");
```

### Combining Datasets

```php
it("validates amount by currency", function (string $currency, float $amount, bool $expected) {
    // 🧪 Arrange
    $validator = new AmountValidator();

    // 🧪 Act
    $result = $validator->isValid(currency: $currency, amount: $amount);

    // 🧪 Assert
    expect($result)->toBe($expected);
})->with("currencies")->with("amounts");
```

## Edge Case Checklist

Always test these scenarios where applicable:

### Boundary Values
- Minimum valid value
- Maximum valid value
- Just below minimum (invalid)
- Just above maximum (invalid)
- Zero / empty / null

### Collections
- Empty collection
- Single item
- Maximum size
- Duplicate items

### Strings
- Empty string
- Whitespace only
- Unicode characters
- Very long strings
- Special characters

### Numerics
- Zero
- Negative numbers
- Decimal precision
- Integer overflow boundaries

### Date/Time
- Leap years (Feb 29)
- Month boundaries (Jan 31 → Feb 1)
- Year boundaries (Dec 31 → Jan 1)
- Timezone transitions
- DST transitions

## Negative Path Testing

Every feature needs negative path tests alongside happy paths:

```php
describe("LeadProcessor", function () {
    describe("positive paths", function () {
        it("processes valid lead successfully", function () {
            // ...
        });

        it("handles optional fields gracefully", function () {
            // ...
        });
    });

    describe("negative paths", function () {
        it("rejects lead with invalid email", function () {
            // 🧪 Arrange
            $lead = Lead::factory()->make(attributes: ["email" => "invalid"]);

            // 🧪 Act
            $result = $this->processor->process(lead: $lead);

            // 🧪 Assert
            expect($result->isValid())->toBeFalse();
            expect($result->errors())->toHaveKey("email");
        });

        it("handles database connection failure", function () {
            // ...
        });
    });

    describe("edge cases", function () {
        it("handles lead at exactly the age boundary", function () {
            // ...
        });
    });
});
```

## Laravel HTTP Tests

```php
it("creates a lead via API", function () {
    // 🧪 Arrange
    $payload = [
        "email" => "test@example.com",
        "first_name" => "John",
    ];

    // 🧪 Act
    $response = $this->postJson(uri: "/api/leads", data: $payload);

    // 🧪 Assert
    $response->assertStatus(status: 201);
    $response->assertJsonStructure(["data" => ["id", "email"]]);
    $this->assertDatabaseHas(table: "leads", data: ["email" => "test@example.com"]);
});
```

### Validation Testing

```php
it("validates required fields", function (string $field) {
    // 🧪 Arrange
    $payload = Lead::factory()->raw();
    unset($payload[$field]);

    // 🧪 Act
    $response = $this->postJson(uri: "/api/leads", data: $payload);

    // 🧪 Assert
    $response->assertStatus(status: 422);
    $response->assertJsonValidationErrors(errors: [$field]);
})->with(["email", "first_name", "last_name", "ssn"]);
```

### Job Testing

```php
it("dispatches lead processing job", function () {
    // 🧪 Arrange
    Queue::fake();
    $lead = Lead::factory()->create();

    // 🧪 Act
    ProcessLeadAction::run(lead: $lead);

    // 🧪 Assert
    Queue::assertPushed(job: ProcessLeadJob::class, callback: function ($job) use ($lead) {
        return $job->lead->id === $lead->id;
    });
});
```

### Event Testing

```php
it("fires event when lead is sold", function () {
    // 🧪 Arrange
    Event::fake();
    $lead = Lead::factory()->create();

    // 🧪 Act
    $lead->markAsSold();

    // 🧪 Assert
    Event::assertDispatched(event: LeadSold::class, callback: function ($event) use ($lead) {
        return $event->lead->id === $lead->id;
    });
});
```

### Factory States

```php
it("applies discount for returning customers", function () {
    // 🧪 Arrange
    $customer = Customer::factory()
        ->returning()
        ->withPurchaseHistory(count: 5)
        ->create();

    // 🧪 Act
    $discount = $this->calculator->calculate(customer: $customer);

    // 🧪 Assert
    expect($discount->percentage)->toBe(15);
});
```

## Architecture Tests

```php
arch("controllers have controller suffix")
    ->expect("App\Http\Controllers")
    ->toHaveSuffix("Controller");

arch("actions are invokable")
    ->expect("App\Actions")
    ->toHaveMethod("__invoke");

arch("models extend base model")
    ->expect("App\Models")
    ->toExtend("Illuminate\Database\Eloquent\Model");

arch("no debugging statements")
    ->expect(["dd", "dump", "ray", "var_dump"])
    ->not->toBeUsed();

arch("strict types everywhere")
    ->expect("App")
    ->toUseStrictTypes();
```

## Test Organization

```
tests/
├── Unit/
│   ├── Actions/
│   ├── Services/
│   └── ValueObjects/
├── Feature/
│   ├── Api/
│   ├── Http/
│   └── Jobs/
├── Architecture/
│   └── ArchitectureTest.php
└── Datasets/
    └── LeadDatasets.php
```

## Grouping with Describe

```php
describe("IncomeNormalizer", function () {
    beforeEach(function () {
        $this->normalizer = new IncomeNormalizer();
    });

    describe("normalize()", function () {
        it("converts weekly to monthly", function () {
            // ...
        });

        it("converts biweekly to monthly", function () {
            // ...
        });
    });

    describe("edge cases", function () {
        it("handles zero income", function () {
            // ...
        });
    });
});
```

## Test Strategy & Quality Metrics

### Risk-Based Test Planning

| Risk Level | Coverage Approach |
|------------|-------------------|
| Critical (financial, security, PII) | 100% coverage, multiple test types |
| High (core features, integrations) | 90%+ coverage, automated regression |
| Medium (standard features) | 80%+ coverage, key scenarios |
| Low (UI polish, edge features) | Manual testing, basic automation |

### Test Type Distribution

```
Unit Tests:        60% (fast, isolated, developer-owned)
Integration Tests: 25% (API contracts, database, services)
E2E Tests:         10% (critical user journeys)
Manual/Exploratory: 5% (edge cases, usability)
```

### Quality Targets

| Metric | Target | Action if Below |
|--------|--------|-----------------|
| Code coverage | 90%+ | Block PR, add tests |
| Test pass rate | 99%+ | Fix immediately |
| Flaky test rate | <1% | Quarantine and fix |
| Avg execution time | <30 min | Optimize or parallelize |

### Flaky Test Management

1. **Identify**: monitor test results for intermittent failures
2. **Quarantine**: move flaky tests to separate suite
3. **Diagnose**: analyze for timing, data, or environment issues
4. **Fix**: address root cause, not symptoms
5. **Validate**: run fixed test 10+ times before restoring

### Test Data Builders

```php
class LeadBuilder
{
    protected array $attributes = [];

    public function withValidSsn(): self
    {
        $this->attributes["ssn"] = "123-45-6789";

        return $this;
    }

    public function withInvalidEmail(): self
    {
        $this->attributes["email"] = "invalid";

        return $this;
    }

    public function build(): Lead
    {
        return Lead::factory()->make(attributes: $this->attributes);
    }
}
```

### CI/CD Pipeline

```yaml
# .github/workflows/tests.yml
test:
  runs-on: ubuntu-latest
  steps:
    - uses: actions/checkout@v4
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: "8.4"
        coverage: xdebug
    - name: Install dependencies
      run: composer install --no-interaction
    - name: Run tests
      run: php artisan test --parallel --coverage --min=90
```

### Execution Strategy

| Stage | Tests | Timeout | Failure Action |
|-------|-------|---------|----------------|
| Pre-commit | Unit tests, linting | 2 min | Block commit |
| PR | Full suite, parallel | 15 min | Block merge |
| Main branch | Full suite + coverage | 20 min | Alert team |
| Nightly | Full suite + slow tests | 60 min | Create issue |
