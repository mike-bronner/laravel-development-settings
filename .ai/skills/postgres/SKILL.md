---
name: postgres
description: >
  PostgreSQL query optimization and database design for Laravel Eloquent projects. Use when writing complex
  queries, optimizing slow Eloquent queries, designing migrations, debugging query performance, working with
  PostgreSQL-specific features (JSONB, arrays, CTEs, window functions), or analyzing execution plans.
---

# SQL Pro — PostgreSQL for Laravel

When writing Eloquent code, also invoke the `laravel` skill to ensure code style compliance (named
arguments, no `empty()`, strict types, etc.).

This skill applies when writing or optimizing database queries in Laravel projects backed by PostgreSQL.
Use Laravel Boost MCP's `DatabaseSchema` and `DatabaseQuery` tools to understand the actual schema before
writing queries.

## PostgreSQL-First Patterns

Always use PostgreSQL-specific features when they're the best tool:

### JSONB Columns
```sql
-- Query JSONB data efficiently (use GIN index)
SELECT * FROM leads WHERE metadata @> '{"source": "web"}';
SELECT * FROM leads WHERE metadata->>'status' = 'active';
```

In Eloquent:
```php
$leads = Lead::whereJsonContains(column: "metadata->source", value: "web")->get();
```

### Array Columns
```sql
SELECT * FROM users WHERE 'admin' = ANY(roles);
```

### CTEs for Complex Queries
```sql
WITH active_leads AS (
    SELECT * FROM leads WHERE status = 'active'
),
lead_counts AS (
    SELECT agent_id, COUNT(*) as total FROM active_leads GROUP BY agent_id
)
SELECT agents.name, lead_counts.total
FROM agents JOIN lead_counts ON agents.id = lead_counts.agent_id;
```

### Window Functions
```sql
SELECT
    id,
    amount,
    SUM(amount) OVER (PARTITION BY agent_id ORDER BY created_at) as running_total,
    ROW_NUMBER() OVER (PARTITION BY agent_id ORDER BY amount DESC) as rank
FROM leads;
```

## Query Optimization

### Execution Plan Analysis
Always check `EXPLAIN ANALYZE` before optimizing:
```sql
EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT) SELECT ...;
```

Key things to look for:
- **Seq Scan** on large tables → needs an index
- **Nested Loop** with high row counts → consider Hash Join via index
- **Sort** with high memory → add index matching ORDER BY
- **Buffers shared read** high → data not in cache, check index coverage

### Index Strategy
- **B-tree** (default) — equality, range, sorting
- **GIN** — JSONB, full-text search, array containment
- **GiST** — geometric, range types, full-text
- **Partial indexes** — when queries always filter on a condition:
```sql
CREATE INDEX idx_active_leads ON leads (agent_id) WHERE status = 'active';
```

### Eloquent-Specific Optimization

**N+1 detection**: always use `with()` at query site, never `$with` property:
```php
// Good — explicit eager loading
$leads = Lead::with(relations: ["agent", "lender"])->where(column: "status", value: "active")->get();

// Bad — lazy loading causes N+1
$leads = Lead::where(column: "status", value: "active")->get();
$leads->each(callback: fn ($lead) => $lead->agent->name); // N+1!
```

**Chunking for large datasets**:
```php
Lead::query()
    ->where(column: "status", value: "pending")
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

## Migration Patterns

- Always add indexes for foreign keys and frequently-queried columns
- Use `->constrained()` for foreign keys, never cascading deletes
- Use specific PostgreSQL column types when appropriate:
```php
$table->jsonb(column: "metadata")->nullable();
$table->timestampTz(column: "processed_at")->nullable();
$table->decimal(column: "amount", total: 10, places: 2);
```

## Dangerous Patterns to Avoid

- **`SELECT *` in production queries** — select only needed columns
- **Missing indexes on polymorphic columns** — always index both `*_type` and `*_id`
- **`LIKE '%term%'`** — use PostgreSQL full-text search or trigram indexes instead
- **Decimal precision loss** — never use float for money, always decimal
- **Raw queries without bindings** — always use parameterized queries to prevent SQL injection
