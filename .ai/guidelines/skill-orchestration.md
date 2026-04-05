# Skill Orchestration

## CRITICAL: Sub-Agent Spawning Required

**THIS RULE IS MANDATORY FOR NON-TRIVIAL TASKS.**

### Pre-Task Checklist

**STOP. Before starting ANY task, verify:**

- [ ] Does this require domain expertise? → **MUST spawn specialist**
- [ ] Does this involve research/analysis/evaluation? → **MUST spawn `research-analyst`**
- [ ] Are there multiple independent sub-tasks? → **MUST spawn agents in parallel**
- [ ] Does user mention: security, test, review, refactor, debug, optimize? → **MUST spawn matching skill**

### If ANY Box is Checked

You MUST spawn sub-agents BEFORE attempting the task yourself.

---

## Core Principle

**Research in parallel, act with approval.** Sub-agents investigate and recommend. File operations still require user approval per `agent-behavior.md`.

---

## Quick Trigger Reference

| If User Says... | Spawn |
|-----------------|-------|
| review, PR, check this | `code-reviewer` |
| test, coverage, edge case | `pest-testing` |
| security, vulnerabilities | `security-auditor` |
| refactor, clean up, simplify | `refactoring-specialist` |
| bug, error, not working | `debugger` |
| migration, schema, database | `database-administrator` |
| evaluate, compare, analyze, research | `research-analyst` |
| find, locate, search | `search-specialist` |
| architecture, design, scalability | `architect-reviewer` |
| payments, transactions, financial | `fintech-engineer` |

### Do NOT Spawn When

- Task is trivial (single-line fix, typo correction)
- Information is already in context
- User explicitly wants inline handling
- Sub-agent would just re-read files already in conversation

---

## Transparency

Always inform the user when spawning sub-agents and summarize findings when complete.
