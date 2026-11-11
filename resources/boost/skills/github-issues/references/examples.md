# Examples

## A bug

Title: Import stops at Psalm 119 when a verse holds two footnotes

Type: PBI. Label: `bug`. Parent: the footnote import epic.

```markdown
## Problem

Where: `php artisan bible:import kjv`, on a fresh database.

Steps to reproduce:

1. Run `php artisan migrate:fresh`.
2. Run `php artisan bible:import kjv`.

Expected: the import finishes with all 31,102 verses.

Actual: the import stops after 14,978 verses, at Psalm 119:105, with this output:

    SQLSTATE[23505]: Unique violation: footnotes_verse_id_position_unique

## Acceptance criteria

- [ ] `php artisan bible:import kjv` imports all 31,102 verses.
- [ ] Psalm 119:105 shows both of its footnotes, in their printed order.
- [ ] A second run of the import adds no verse and reports no error.
```

The body names the command and the steps. It quotes the output exactly. It never
guesses why the unique index fails, and it never proposes a change to it.

## An epic

Title: Readers see every footnote of the King James Version

Type: Epic. No kind label. Parent: the Product Goal for a complete King James
Version.

```markdown
## Goal

Every footnote of the printed King James Version shows beside its verse.

## Why

Readers compare translations through the footnotes. Today 9,837 of 37,762
footnotes are missing.

## Outcomes

- Each footnote shows beside the verse it belongs to.
- Footnotes keep their printed order within a verse.
- A reader can hide the footnotes.
```

The body never lists the epic's PBIs. GitHub shows them as sub-issues. The epic
opened with a research PBI and one bug. More PBIs follow as the research answers
arrive.
