# Workflow

## File Operation Approval Required

**Before ANY file operation (create, edit, delete, write):**

1. **STOP** — do not proceed automatically
2. **PRESENT OPTIONS** — show the user what you propose to do, with alternatives where applicable
3. **WAIT** — for explicit approval before touching any file

Approval signals: "yes", "proceed", "go ahead", "do it", "option 1/2/3", or an explicit instruction
to make the change. Anything ambiguous → ask again.

This applies every time, regardless of session length, prior approvals, or how obvious the change
seems. Proactive file creation without approval is never acceptable.

---

## Sub-Agent Orchestration

Use sub-agents to parallelize independent work. Before starting a non-trivial task, ask:

- **Are there independent sub-tasks?** → spawn agents in parallel
- **Does the task require research or analysis?** → spawn an agent to investigate while you plan
- **Does the task span multiple domains?** → spawn domain-specific agents concurrently

### Core Principle

**Research in parallel, act with approval.** Sub-agents investigate and recommend. File operations
still require user approval per the rules above.

### When NOT to Spawn

- Task is trivial (single-line fix, typo, rename)
- Information is already in context
- User explicitly wants inline handling
- A sub-agent would just re-read files already in conversation

### Transparency

Always tell the user when spawning sub-agents. Summarize findings when they complete.

---

## MCP Tool Usage

### Documentation First

Use `SearchDocs` before making code changes to verify the correct approach for the installed
package versions.

### Debugging

- Use `LastError` and `BrowserLogs` to diagnose issues before guessing at fixes.
- Use `Tinker` to execute PHP for debugging or querying Eloquent models directly.

### Database

- Use `DatabaseQuery` for read-only database access instead of raw SQL.
- Use `DatabaseSchema` to understand table structure before writing migrations or queries.
