# Conventional Commits 1.0.0 — Quick Reference

Source: [conventionalcommits.org/en/v1.0.0](https://www.conventionalcommits.org/en/v1.0.0/)

## Structure

```
<type>: <description>

[optional body]

[optional footer(s)]
```

## Types

| Type | Purpose | SemVer |
|------|---------|--------|
| `feat` | Introduce a new feature | MINOR |
| `fix` | Fix a bug | PATCH |
| `docs` | Documentation only | — |
| `style` | Formatting, whitespace (not CSS) | — |
| `refactor` | Code change that neither fixes nor adds | — |
| `perf` | Performance improvement | — |
| `test` | Add or update tests | — |
| `build` | Build system or external dependencies | — |
| `ci` | CI configuration and scripts | — |
| `chore` | Maintenance tasks | — |

## Breaking Changes

Two ways to indicate a breaking change (correlates to SemVer MAJOR):

```
feat!: remove deprecated endpoints

BREAKING CHANGE: The /v1/users endpoint has been removed.
```

- Append `!` immediately before the `:` in the type prefix, **or**
- Add a `BREAKING CHANGE:` footer (must be uppercase)
- Both can be used together

## Body

Optional. Begins one blank line after the description. Free-form, can contain multiple paragraphs:

```
fix: prevent race condition in lead processing

The previous implementation allowed concurrent requests to create
duplicate leads. Added a database-level unique constraint and
optimistic locking to prevent this.
```

## Footers

Optional. Begin one blank line after the body. Follow git trailer format:

```
fix: correct minor typos in code

Reviewed-by: Z
Refs: #123
```

- Tokens use `-` in place of spaces (exception: `BREAKING CHANGE`)
- Separator is either `: ` or ` #`

## Rules

1. Type is **required** — must be a noun (`feat`, `fix`, etc.)
2. Description is **required** — immediately after `: `
3. Body is **optional** — blank line after description
4. Footers are **optional** — blank line after body
5. `BREAKING CHANGE` must be **uppercase**
6. All other type units are **case-insensitive**
7. `feat` and `fix` are the only types defined by the spec — others are conventions
