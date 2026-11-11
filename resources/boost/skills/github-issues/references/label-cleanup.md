# Label cleanup

Use this guide to move a repository from its old labels to the label set of this
skill. The goal state has four kind labels: `bug`, `enhancement`, `research` and
`maintenance`. Every open PBI carries exactly one of them. Native GitHub features
carry everything else.

Deleting a label removes it from every issue and pull request that has it, open or
closed. Remove or delete no label before the repository owner approves the map.

## Steps

1. List the labels:

   ```bash
   gh label list --limit 200
   ```

2. For each label, count the issues and the pull requests that carry it, open or
   closed. Replace `OWNER/REPO` with the repository and the label with its name:

   ```bash
   gh api -X GET search/issues -f q='repo:OWNER/REPO is:issue label:"needs:decision"' --jq .total_count
   gh api -X GET search/issues -f q='repo:OWNER/REPO is:pr label:"needs:decision"' --jq .total_count
   ```

3. Map each old label to its new home with the table below. Write the map down as
   a table of old label, new home, issue count and pull request count. Show it to
   the repository owner.
4. Wait for the owner to approve the map. Change no label until they do.
5. Create each kind label that is missing:

   ```bash
   gh label create research --description "A question to answer before the work it leads to can be planned"
   gh label create maintenance --description "Upkeep that changes no behavior"
   ```

6. For each issue with an old label, set the new home first. Then remove the old
   label.

   ```bash
   gh issue edit 23 --add-label maintenance --remove-label "area:documentation"
   ```

   Remove the old label from each pull request that carries it too:

   ```bash
   gh pr edit 24 --remove-label "area:documentation"
   ```

7. Give each open PBI exactly one kind label. If an issue holds two kinds, it holds
   two problems. Split it as the rewrite workflow describes.
8. Run both counts from step 2 again for each old label. Delete the label only when
   both counts are 0:

   ```bash
   gh label delete "area:documentation" --yes
   ```

9. Report the map, and every issue that still needs a decision from the owner.

## Map

| Old label | New home |
|---|---|
| `feature`, `improvement`, `request` | `enhancement` |
| `defect`, `regression`, `error` | `bug` |
| `question`, `spike`, `investigation`, `audit` | `research`, when it is work to plan. Otherwise a comment or a discussion. |
| `documentation`, `docs`, `dependencies`, `chore`, `refactor`, `tech debt` | `maintenance` |
| `epic`, `goal`, `task`, `story` | The issue type. Remove the label. |
| Any `area:` label, or a label that names a part of the product | Remove it. The title and the repository say where. |
| `needs decision`, `needs clarification`, `needs verification`, `blocked`, `deferred`, `in progress`, `wip` | The project's Status field. A blocker becomes a blocked-by relationship. |
| `priority`, `p1`, `effort`, `size`, `estimate` | An issue field or a project field. |
| `duplicate` | Close with the reason "duplicate" and the link to the issue it repeats. |
| `wontfix`, `invalid` | Close with the reason "not planned" and a one-line comment. |
| A label that groups a body of work, such as a release or a theme | A Product Goal or an epic, with the issues as its sub-issues. |

The same rule covers a prefixed name. For example, `needs:decision` maps as
`needs decision` does, and `disposition:wontfix` maps as `wontfix` does.

Close an issue with a reason like this:

```bash
gh issue close 31 --duplicate-of 18
gh issue close 32 --reason "not planned" --comment "The import no longer reads this format."
```

Keep a label that is not in the map only when it serves one use that no kind label
and no native feature covers. Ask the owner about each one.
