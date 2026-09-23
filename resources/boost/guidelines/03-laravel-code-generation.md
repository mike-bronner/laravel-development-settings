# Mandatory Skills for Laravel Development

This project has specialized skills that enforce strict conventions. Invoke the relevant skills
before starting work — the project's Pint linter will reject code that doesn't follow them.

## Always invoke before ANY PHP or Blade change:
- **`laravel`** — 16 absolute code style rules (named arguments, no `empty()`, no `else`, custom
  indentation, etc.) plus reference guides, Boost MCP usage, and lint verification workflow.
  Invoke on every PHP task — the rules are too specific to memorize from a single read.

## Invoke when your task enters these domains:
- **`postgres`** — when writing Eloquent queries, migrations, or optimizing query performance.
  PostgreSQL-specific features, execution plan analysis, and index strategy.
- **`security`** — when handling authentication, authorization, PII/PHI fields, or auditing for
  vulnerabilities. HIPAA requirements, encryption patterns, and Laravel security checklist.
- **`a11y`** — when writing or modifying Blade templates or Livewire components that users interact
  with. WCAG 2.1 AA, ARIA patterns, keyboard navigation, live regions.
- **`architecture`** — when planning features, evaluating domain boundaries, or deciding where code
  lives. Actions vs Services, DDD-lite structure, controller thinness.
- **`refactor`** — when restructuring existing code, extracting Actions, breaking up models, or
  reducing complexity. Safe refactoring workflow and code smell thresholds.
