---
name: github-issues
description: >-
  Write, review and rewrite GitHub issues so that a human understands each one in
  one pass and an AI agent can act on it. Use this skill whenever you create, draft,
  review, split or rewrite an issue, a PBI, a bug report, an epic or a Product Goal,
  add sub-issues to an epic, or choose, add or clean up issue labels in a repository.
  Use it too whenever you write, fill, review or rewrite a pull request description.
---

# GitHub Issues

An issue states one problem and what the experience looks like when it is fixed.
A human must understand it in one pass. An AI agent must be able to act on it
without asking a question.

## Why short issues

A study of 3,180 pull requests by GitHub Copilot measured what makes an issue
work ("What Makes a GitHub Issue Ready for Copilot?", arXiv 2512.21426).

- A longer issue body lowered the chance of a merged pull request by about 9 percent.
- A longer comment thread lowered it by about 11 percent.
- A well-scoped issue was merged about 16 percent more often.
- A self-contained issue was merged about 17 percent more often.
- An issue with clear expected and edge-case behavior was merged about 11 percent more often.
- Naming the files or components involved helped by about 6 to 7 percent.

This skill draws its rule from these findings. An issue shows exactly where and
how the problem occurs. It never says what causes the problem or how to fix it.

## Issue types

Every issue has one of three GitHub issue types.

- **Product Goal**: a future state of the product. It is the long-term target that
  the work plans against. It never carries a pull request itself. Its work lives
  in its children.
- **Epic**: an umbrella under a Product Goal. It keeps each PBI small, so that the
  work never becomes one huge PBI.
- **PBI** (Product Backlog Item): every item of actual work, whatever its kind.
  A feature, a bug, research and maintenance are all PBIs.

There is no Task type. The kind of work is a label. A type names a kind of work,
never a size.

Issue types belong to the organization that owns the repository. A personal
repository offers only GitHub's default types (Task, Bug and Feature), and has no
Product Goal, Epic or PBI. In a repository that lacks these types, leave the type
empty. The parent links and the kind labels still carry the structure.

## Hierarchy and size

The hierarchy is Product Goal, then Epic, then PBI.

- A PBI usually sits under an epic. It may sit straight under a Product Goal when
  no epic is needed.
- Exactly one pull request implements a PBI. Ideally a PBI is a quick unit of work
  that ships on its own.
- A PBI never has sub-issues. Its acceptance criteria are a checklist inside it.
  A sub-issue under a PBI gets picked up as separate work. One PBI then needs more
  than one pull request.
- One problem goes in one issue. Split two problems into two PBIs under an epic.
- Record a dependency only as GitHub's blocked-by relationship. Never write it in
  the body.
- Connect related issues through their Product Goal, their epic or the
  relationship fields. A label never groups a body of work.

## Epics and their sub-issues

- The epic body defines the larger goal.
- The epic body never lists its children. GitHub already shows them as sub-issues.
- Add each sub-issue when you understand it. Open the epic with the PBIs that are
  clear now. Add the others as research answers arrive.
- Each sub-issue is a full PBI. Write and review it as an issue of its own.
- Set a blocker between two sub-issues as a blocked-by relationship.

## Research

Research is tracked as work. It differs from other work because it leads to new
issues.

- A research PBI carries the `research` label. It leads its epic.
- A research PBI is done when two things are true. Its questions have answers, and
  its follow-up PBIs exist under the same epic. Without the follow-up PBIs, the
  answers lead nowhere.
- A small one-off question may stay a research PBI with no epic.
- The answers and the evidence go in comments on the research PBI.

## Labels

- A label is one flat plain word. Never give it a prefix or a category, such as
  `work:`, `area:` or `needs:`.
- Each label serves one use that no other label and no native GitHub feature
  covers.
- Every PBI carries exactly one kind label:
  - `bug`: the product does something wrong.
  - `enhancement`: the product gains or changes a behavior.
  - `research`: a question to answer before the work it leads to can be planned.
  - `maintenance`: upkeep that changes no behavior. Documentation and dependency
    work count as maintenance.
- A Product Goal and an epic carry no kind label. Their type says what they are.
- There are no area labels. The title and the repository say where.

Everything else uses a native GitHub feature, never a label.

| Use | Native home |
|---|---|
| Product Goal, Epic or PBI | Issue type |
| Blocker | Blocked-by relationship |
| Priority, effort | Issue fields or project fields |
| Needs a decision, deferred, in progress | The project's Status field |
| Duplicate | Close reason "duplicate", with the link to the issue it repeats |
| Won't fix, invalid | Close reason "not planned", with a one-line comment |

To adopt this label set in a repository, follow
[references/label-cleanup.md](references/label-cleanup.md).

## What the body holds

An issue holds:

- the problem that needs to be fixed
- what the experience looks like when it is fixed
- exactly where and how the problem shows, so that anyone can reproduce it. Name
  the command, the page, the passage or the entry, and the steps.

An issue never:

- prescribes an implementation
- states what causes the problem
- states what the correct fix is

Evidence and investigation go in comments, never in the body.

These never go in the body either:

- status updates
- authorization or timebox notes
- assignment history
- licensing correspondence
- template filler, such as browser, operating system or "business value"
- a pointer to a file, a note or a chat the reader cannot open

### Sections per kind

Use only the sections that the kind prescribes, in this order.

| Kind | Sections |
|---|---|
| Product Goal | Goal (the future state), Why, How success is measured |
| Epic | Goal, Why, Outcomes |
| `bug` | Problem (where it happens, steps to reproduce, expected and actual), Acceptance criteria |
| `enhancement`, `maintenance` | Problem, Acceptance criteria |
| `research` | Question, Why it matters, Acceptance criteria (the questions answered and the follow-up PBIs created) |

A PBI's acceptance criteria define its scope. An epic's Outcomes define its scope.
Anything they do not name is out of scope.

The repository's issue templates in `.github/ISSUE_TEMPLATE/` hold these sections.
They are Markdown files, never YAML issue forms. `gh issue create --template`
reads only Markdown templates. It cannot use a YAML issue form.
[references/examples.md](references/examples.md) shows a finished bug and a
finished epic.

### Title

- State the symptom or the outcome, never the cause or the fix.
- Keep it under about 12 words.
- Name the feature, what breaks and when, for a bug.
- Never add a prefix such as `[FEATURE]` or `[RESEARCH]`. The type and the label
  already say it.

### Acceptance criteria

- Write them as a `- [ ]` checklist of outcomes.
- Make each outcome observable from outside the system.
- Write 2 to 6 items for a PBI.
- Use Given, When and Then only when an outcome depends on a condition. It maps
  well to tests. It also makes an item about three times longer.
- The author of the issue writes them. Never leave them for someone else to add.

### Length

- A PBI body has at most about 250 words. This is a soft cap.
- An epic body may be longer when its goal needs it. This is a soft cap too.

## Writing rules

Write by these rules from the first draft.

**Density**

- Say only what the reader needs to act. A true detail that does not change the
  work goes in a comment.
- Never restate the title, the parent epic or another issue. Link the issue as a
  relationship instead.
- Give numbers, not adjectives. Write "9,837 of 37,762 verses", not "a large part
  of the verses".
- Add no filler section, such as Summary, Background, Business Value,
  Confirmation or Environment.

**Sentences**

- Put one idea in each sentence, in about 20 words at most.
- Use the active voice and the simple tense.
- Use no semicolons and no em dashes. If you want one, you have two sentences.
- Use no hedges, such as "may potentially" or "it is worth noting".
- Use no marketing adjectives, such as "seamless" or "robust".

**Words**

- Use one term for each concept, the same way throughout.
- Use plain words before jargon. Define a project term once, or leave it out. A
  code such as "HD-C1" or "Child B" means nothing to a new reader.
- Use the verb, not a noun made from it. Write "decide", not "make a
  determination".

**Shape**

- Add no heading that the kind does not prescribe.
- Use bold text rarely, never in every sentence.
- Use a table only for tabular data. Use a list for three or more parallel items.
- Quote error text and command output exactly, in a code block.
- The reader sees only the issue. Restate what they need, and never point at a
  file or note they cannot open.

## Self-check

Answer these four questions before you post. Fix the issue until every answer is
right.

1. Is the body within its word cap?
2. Does each sentence hold one idea?
3. Does any sentence state a cause or a fix? It must not.
4. Can a stranger reproduce the problem from the body alone?

## Workflow: write an issue

1. Pick the type: Product Goal, Epic or PBI.
2. For a PBI, pick exactly one kind label.
3. Check that the issue holds one problem. If it holds two, write two PBIs under
   one epic.
4. Search the open issues for the same problem. If one exists, add to it instead.
5. Write the title.
6. Write the sections of its kind, and nothing else.
7. Write the acceptance criteria.
8. Run the self-check, and fix each failure.
9. Create the issue. Set the kind label, the type, the parent and any blocker as
   you create it. For example:

   ```bash
   gh issue create --title "Import stops at Psalm 119 when a verse holds two footnotes" \
     --body-file body.md --label bug --type PBI --parent 41 --blocked-by 37
   ```

   `gh issue create --template` takes only the template's body. It never sets the
   template's labels, so pass `--label` yourself. Leave out `--type` in a
   repository that lacks the type.
10. Put the evidence and the investigation in the first comment.

For an epic, create the epic first. Then create each PBI that is clear now with
`--parent` set to the epic.

## Workflow: review an issue

A review reports. It changes nothing: no edit, no label, no comment and no
relationship.

1. Read the issue: its title, body, type, labels, parent, sub-issues and
   relationships.
2. Check each rule in this skill against it:
   - the type and the hierarchy
   - exactly one kind label on a PBI, and no label that a native feature covers
   - one problem only
   - the title
   - the sections of its kind, and no others
   - no cause, no fix and no implementation
   - where and how the problem shows
   - the acceptance criteria
   - the length
   - the writing rules
   - nothing from the never-in-the-body list
   - for an epic, no list of its children in the body
3. Run the self-check.
4. Report each break: the rule it breaks, and the words that break it, quoted.
   Name what the issue needs, never the code change.
5. If nothing breaks, say so in one line.

## Workflow: rewrite an issue

The original body moves to a comment before the body changes. Nothing is lost.

1. Review the issue first, as the review workflow describes.
2. Find the work already done, and where: the repository, the pull request or the
   commit. Read the linked pull requests and the comments.
3. Post one comment that holds the original body, word for word. Above it, state
   that this is the original body, moved before the rewrite. Then list what is
   already done and where. For example:

   ```markdown
   Original body, moved here before the rewrite.

   Already done:
   - Footnote import for Genesis, in pull request #52.

   ---

   <the original body, word for word>
   ```

   A tool that checks your style may refuse the original's punctuation, such as
   its semicolons or em dashes. Then change only that punctuation, and say so in
   the comment.
4. Confirm that the comment exists before you go on.
5. Rewrite the body with the remaining work only, in the shape of its kind.
6. Fix the title, the type and the kind label.
7. Move a dependency written in the body to a blocked-by relationship.
8. For an epic, make each child listed in the body a sub-issue. Then delete the
   list. Review and rewrite each sub-issue as an issue of its own.
9. If the issue holds two problems, keep one. Write a new PBI for the other under
   the same epic.
10. Run the self-check on the new body.

If no work remains, close the issue as completed. The comment from step 3 says
where the work was done.

## Workflow: clean up labels

Follow [references/label-cleanup.md](references/label-cleanup.md) to move a
repository's old labels to the label set above.

## Pull requests

A pull request body tells a reviewer what changed, why, and what they must know
before they approve. It stops there. The writing rules above apply to it too.

### Title

- Write the title as a Conventional Commit, such as
  `fix: import every verse that holds two footnotes`.
- Keep it under about 12 words.

### Sections

The template at `.github/pull_request_template.md` holds these sections, in this
order.

- **Related issues**: one bullet for each issue that the pull request relates
  to, one issue per bullet. A bullet starts with a closing keyword only for an
  issue that the pull request closes.
- **What changed**: one to three bullets about behavior. It is never a list of
  files.
- **Why**: the reason for the change when the linked issue gives none. It also
  gives a short reason for each choice that departs from the issue, from the
  project's usual patterns or from a stated requirement. It never repeats the
  reasoning that the issue already gives. Leave the section out when it is
  empty.
- **QA steps**: a numbered playbook that a human follows in the browser or the
  console. Each step gives one action and what the human should see after it.
  Add a screen recording for a change to the UI. Leave the section out only when
  the change has no behavior that a human can try.
- **Verified**: the test command and its pass count, and the CI result. It holds
  only automated evidence.
- **Before you approve**: breaking changes, migrations, config or cache effects,
  a rollback that is not a plain revert, and known gaps as a short list. Leave
  the section out when it is empty.

A filled body has at most about 250 words. This is a soft cap. The QA steps do
not count toward it, because a human needs every step. Keep each step short.

### Issue keywords and the base branch

- GitHub closes an issue when a bullet names it after a closing keyword: close,
  closes, closed, fix, fixes, fixed, resolve, resolves or resolved. Letter case
  does not matter.
- Use `Fixes` for a bug. Use `Closes` or `Resolves` for other work. This is a
  convention. Every keyword closes the issue the same way.
- Give each issue its own keyword. GitHub needs the full syntax for each issue
  that it closes.
- A keyword closes the issue only when the pull request merges into the default
  branch.
- On any other base branch, GitHub ignores the keyword. It creates no link,
  and the merge leaves the issue open. Close the issue by hand when the work
  reaches the default branch.
- To link an issue without closing it, write `Part of #<number>` or
  `Related to #<number>`. The issue shows the pull request in its timeline and
  stays open. Use this for an epic, or for an issue that the pull request only
  advances.

### Acceptance criteria stay in the issue

- The acceptance criteria live only in the linked issue. The pull request body
  never copies or restates them.
- Whoever works the pull request ticks each criterion in the issue as soon as it
  is met.
- Read the issue body again just before each edit, never from an earlier copy.
  Someone may have changed the issue since your last read, and an edit made from
  an old copy overwrites that change. For example:

  ```bash
  gh issue view 57 --json body --jq .body > issue.md
  # change "- [ ]" to "- [x]" on each criterion that is met
  gh issue edit 57 --body-file issue.md
  ```

### Never in a pull request body

- a copy of the acceptance criteria
- reasoning that the linked issue already gives
- rejected alternatives or red-then-green transcripts. They go in the commit
  messages.
- a checklist of what CI already enforces
- type-of-change boxes
- process steps
- a request for a review, or instructions for an AI reviewer
- a guidance line left from the template

### Workflow: write a pull request

1. Read the repository's pull request template. Look in `.github/`, in the root
   and in `docs/`, and accept any letter case in the file name.
2. Fill each section by the rules above. Leave out a section that the rules leave
   out.
3. Delete every guidance line of the template. In the shipped template, each
   one starts with `>`. Another template may hold its guidance in HTML comments.
4. Check the body against the word cap and the never-in-the-body list.
5. Tick each met acceptance criterion in the linked issue.
6. Create the pull request, and pass the filled body as a file. For example:

   ```bash
   gh pr create --title "fix: import every verse that holds two footnotes" \
     --body-file body.md
   ```

   `gh pr create` applies no template when it runs without a terminal. It also
   refuses `--template` together with `--body-file`. So fill the template
   yourself, and always pass it with `--body-file`. Pass `--base` when the base
   is not the default branch.
7. Never request a reviewer. Never pass `--reviewer`, never add a reviewer later,
   and never post a comment that asks a person or a bot to review. The owner of
   the repository requests every review.

[references/examples.md](references/examples.md) shows a finished pull request.

### Workflow: review a pull request description

A review reports. It changes nothing.

1. Read the title, the body, the base branch and each linked issue.
2. Check each rule in this section against it:
   - the title
   - one bullet for each related issue, with a closing keyword only for an issue
     that it closes
   - the sections, in order, and no others
   - the length
   - no copy of the acceptance criteria
   - nothing from the never-in-the-body list
   - the writing rules
3. Report each break: the rule it breaks, and the words that break it, quoted.
4. If nothing breaks, say so in one line.
