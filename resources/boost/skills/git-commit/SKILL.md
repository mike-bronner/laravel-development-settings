---
name: git-commit
description: >-
  Generate commit messages using Conventional Commits + Gitmoji format. Use this
  skill whenever creating, drafting, or suggesting git commit messages — including
  /commit commands, pre-commit hooks, bulk commits, and any context where a commit
  message is being composed. Always invoke this skill before writing a commit message.
---

# Git Commit Messages

## Format

```
<type>: <emoji> <description>.

<optional body>

<optional footer(s)>
```

The colon, emoji, and description each separated by exactly one space: `type: emoji description.`

## Rules

1. Description starts with the appropriate gitmoji, one space after `:`
2. Description ends with a period
3. No scopes — ever
4. Only reference real GitHub issues in footers
5. Always consult `references/gitmoji.md` to select the correct emoji for the change
6. Always consult `references/conventional-commits.md` to determine the correct type and overall format for the commit message, based on SemVer mapping and breaking change syntax

## Examples

```
feat: ✨ Add email validation endpoint.
```

```
fix: 🐛 Resolve checkout payment error.
```

```
refactor: ♻️ Extract payment processing into service class.
```

```
fix: 🐛 Resolve token expiration bug.

Fixes: #789
Fixes: #790
```

## Body & Footers

For details on body paragraphs, footer format, and breaking change indicators, read `references/conventional-commits.md`.

## Issue References

Only include for real issues being fixed. Each on its own line in the footer:

```
Fixes: #789
Fixes: #790
```
