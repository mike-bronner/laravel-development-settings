# Debugging Laravel Applications

## Laravel-Specific Tools

```php
// Dump and die for quick inspection
dd($variable);

// Dump without stopping execution
dump($variable);

// Log to Laravel log
Log::debug("Checkpoint reached", ["data" => $data]);

// Query debugging
DB::enableQueryLog();
// ... code ...
dd(DB::getQueryLog());

// Interactive exploration
// php artisan tinker
```

## Laravel Boost MCP for Debugging

Use these Boost tools when diagnosing issues:

- **`LastError`** — get the most recent application error
- **`ReadLogEntries`** — read application log files (pass entry count)
- **`BrowserLogs`** — browser console logs for frontend issues
- **`Tinker`** — execute diagnostic code in application context
- **`DatabaseQuery`** — run read-only queries to verify data state

## Diagnostic Approach

### Phase 1: Information Gathering
1. What is the observable behavior?
2. Stack traces, logs, error messages?
3. When did it start? What changed?
4. Who/what is affected?
5. Is this recurring? Related to other issues?

### Phase 2: Hypothesis Formation
1. List possible causes based on evidence
2. Rank by likelihood and impact
3. Design experiments to test each hypothesis
4. Start with most likely cause

### Phase 3: Systematic Elimination
1. Test hypothesis with minimal reproduction
2. Collect evidence (confirm or refute)
3. Move to next hypothesis if refuted
4. Document findings at each step

## Error Pattern Analysis

| Pattern Type | What to Look For |
|--------------|------------------|
| Time-based | Errors at specific times (cron, peak load, maintenance) |
| User-based | Specific users, roles, or permissions |
| Data-based | Specific input values, data shapes |
| Service-based | Specific service, endpoint, or dependency |
| Version-based | After deployments, library updates |

## Common Laravel Bug Patterns

### N+1 Query Problem
**Symptom:** slow page load, many similar queries in query log
```php
// Fix: eager loading
$leads = Lead::with(relations: ["lender", "provider"])->get();
```

### Null Reference
**Symptom:** "Trying to get property of non-object"
```php
// Fix: null-safe operator
$name = $lead->lender?->name ?? "Unknown";
```

### Race Condition in Cache
**Symptom:** intermittent wrong data
```php
// Fix: atomic locks
Cache::lock(name: "key")->block(seconds: 5, callback: function () {
    // Critical section
});
```

### Memory Exhaustion
**Symptom:** "Allowed memory size exhausted"
```php
// Fix: chunking or lazy collections
Lead::query()->lazy()->each(callback: function ($lead) {
    // Process one at a time
});
```

## Cascade Patterns

When one error triggers others, find the first error in the chain (often NOT the loudest):

- **Retry Storm**: failed request → retry → more load → more failures
- **Connection Pool Exhaustion**: slow query → blocked connections → timeout cascade
- **Queue Backup**: failed job → retry backlog → memory exhaustion
- **Cache Stampede**: cache expires → all requests hit DB → DB overload

## Root Cause Techniques

### Five Whys

```
Problem: Lead processing failed
Why? → Database timeout
Why? → Query took too long
Why? → Missing index on created_at
Why? → Migration wasn't run
Why? → Deployment script skipped migrations
Root cause: Deployment process gap
```

### Fault Tree Analysis

```
Lead Not Processed
├── Input Invalid
│   ├── Missing required field
│   └── Invalid format
├── Processing Failed
│   ├── Database error
│   │   ├── Connection timeout
│   │   └── Constraint violation
│   └── External API failure
│       ├── Network error
│       └── Rate limited
└── System Error
    ├── Out of memory
    └── Queue worker crashed
```

## Production Debugging

### Non-Intrusive Techniques

```bash
# Application logs
tail -f storage/logs/laravel.log

# Queue workers
php artisan horizon:status

# Database connections
mysql -e "SHOW PROCESSLIST"
```

### Safe Practices
- Never add debugging code directly to production
- Use feature flags to enable verbose logging
- Sample requests (1%) for detailed tracing
- Set up monitoring before you need it

## Postmortem Template

```markdown
## Incident: [Title]

### Timeline
- [Time]: First error detected
- [Time]: Investigation started
- [Time]: Root cause identified
- [Time]: Fix deployed

### Root Cause
[Clear explanation]

### Impact
- Duration: X hours
- Users affected: X

### Prevention
- [ ] Test added
- [ ] Monitoring added
- [ ] Documentation updated

### Lessons Learned
[What we'll do differently]
```
