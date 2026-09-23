---
name: security
description: >
  Security auditing for Laravel applications handling PII and healthcare data (HIPAA). Use when reviewing
  authentication/authorization flows, auditing data handling practices, checking for OWASP vulnerabilities
  in Laravel code, reviewing middleware security, assessing PII/PHI exposure, or evaluating compliance
  posture of a Laravel application. Also trigger when reviewing any endpoint that reads or writes user
  data, even if PII isn't explicitly mentioned — most user-facing endpoints handle some form of personal
  data. Invoke proactively when creating new API endpoints, form handlers, or data export features.
---

# Security Auditor — Laravel PII/HIPAA Focus

When writing or modifying PHP code as part of a security fix, also invoke the `laravel` skill to ensure
code style compliance.

This skill audits Laravel applications that handle personally identifiable information (PII) and protected
health information (PHI) subject to HIPAA compliance.

## PII/PHI Data Handling

### What Counts as PII in This Context
- SSN, date of birth, full name + address combinations
- Email addresses, phone numbers (when linked to identity)
- Financial data (income, bank accounts, credit scores)
- Health/medical information (PHI under HIPAA)
- Any unique identifier that can trace back to an individual

### Required Controls for PII Fields
1. **Encryption at rest** — use Laravel's `encrypted` cast or database-level encryption
2. **Encryption in transit** — TLS everywhere, no HTTP endpoints
3. **Access logging** — log who accessed PII and when
4. **Masking in logs** — never log full SSN, only last 4 digits
5. **Masking in responses** — API responses mask sensitive fields by default
6. **Retention limits** — PII has defined retention period, automated purge

```php
// Model cast for encrypted fields
protected function casts(): array
{
    return [
        "ssn" => "encrypted",
        "date_of_birth" => "encrypted:date",
    ];
}
```

### HIPAA-Specific Requirements
- **Minimum necessary rule** — only access PHI needed for the task
- **Audit trail** — all PHI access must be logged (who, when, what, why)
- **BAA coverage** — verify all third-party services handling PHI have signed BAAs
- **Breach notification** — 60-day notification requirement for breaches affecting 500+ individuals

## Laravel Security Checklist

### Authentication & Authorization
- [ ] Passwords hashed with bcrypt/argon2 (Laravel default — don't change)
- [ ] Rate limiting on login (`ThrottleRequests` middleware)
- [ ] Session timeout configured (especially for PHI access)
- [ ] MFA enforced for admin/PHI access roles
- [ ] Policies used for all authorization (not just middleware)
- [ ] API tokens scoped with minimal permissions (Sanctum abilities)

### Middleware Security Patterns
- [ ] Sensitive routes grouped under `auth`, `verified`, and role-checking middleware
- [ ] PHI routes use a dedicated middleware that enforces audit logging on every access
- [ ] API routes use `throttle` middleware to prevent brute-force/enumeration attacks
- [ ] No business logic in middleware — only authentication, authorization, and request filtering

### Input Validation
- [ ] All inputs validated via Form Request classes
- [ ] No raw user input in queries (use Eloquent bindings)
- [ ] File upload validation (type, size, content inspection)
- [ ] JSON input validated with strict schemas

### Output Security
- [ ] Blade `{{ }}` used everywhere (auto-escapes XSS)
- [ ] `{!! !!}` only for trusted content, never user input
- [ ] API responses use Resource classes (control what's exposed)
- [ ] Sensitive fields excluded from `$fillable` and API responses
- [ ] Error pages don't leak stack traces in production

### Session & CSRF
- [ ] CSRF protection on all state-changing routes (`@csrf` in forms)
- [ ] Session cookie `secure`, `httponly`, `samesite=lax`
- [ ] Session regeneration on login (`$request->session()->regenerate()`)

### Database
- [ ] No raw SQL with string concatenation
- [ ] Foreign key constraints (no cascading deletes — explicit control)
- [ ] Soft deletes for auditable records (PHI/PII must be recoverable during retention)
- [ ] Database credentials not in code (use env vars)

## Common Laravel Vulnerabilities

| Vulnerability | How it appears | Fix |
|--------------|----------------|-----|
| Mass assignment | `Model::create($request->all())` | Use Form Requests with explicit `$validated` |
| SQL injection | `DB::raw("WHERE id = $id")` | Use bindings: `DB::raw("WHERE id = ?", [$id])` |
| XSS | `{!! $userInput !!}` | Use `{{ $userInput }}` |
| IDOR | `/api/leads/{id}` without policy | Add Policy check in controller/Form Request |
| Info disclosure | Detailed errors in production | Set `APP_DEBUG=false` |
| Open redirect | `redirect($request->input("url"))` | Validate URL against allowlist |

## Audit Process

When reviewing a Laravel application:

1. **Check `.env.example`** — verify no secrets, review what's configurable
2. **Review middleware stack** — verify auth, throttle, CORS are applied correctly
3. **Scan Form Requests** — verify all endpoints have validation
4. **Check Policies** — verify authorization isn't just middleware-based
5. **Review API Resources** — verify PII fields are masked/excluded
6. **Check logging config** — verify PII isn't written to logs
7. **Review queue jobs** — verify PHI in job payloads is encrypted
8. **Check third-party packages** — verify no known vulnerabilities (`composer audit`)
