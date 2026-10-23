# Pest Test Impact Analysis

Pest 5 can run only the tests a change affects (`--tia`). The first local `--tia` run records a dependency graph of the whole suite, and that run is slow. The `tia-baseline.yml` workflow, synced by laravel-development-settings, publishes that graph from CI so you can download it instead of recording it. It records on pushes to the default branch when that branch is `main`, `master`, `develop` or `production`. A repository whose default branch has another name records on a manual run only.

## Use the CI baseline

- Run `git fetch origin` first. The baseline names the commit it was recorded at, and Pest finds what changed by comparing your work against that commit. A commit your clone does not hold leaves Pest unable to see your changes.
- Run `vendor/bin/pest --tia --baselined`. With no local graph, Pest downloads the latest baseline and runs only the affected tests. `pest()->tia()->baselined()` in `tests/Pest.php` makes this the default.
- Run `vendor/bin/pest --tia --baselined --refetch` to replace the local graph with the latest baseline. After a lookup that found no baseline, Pest waits 24 hours before it looks again on its own. `--refetch` looks at once.
- Pest downloads the baseline with the `gh` CLI. Install it and run `gh auth login`, with a token that can read the repository's Actions.
- Recording and refreshing edges need pcov, or Xdebug in coverage mode.

## What makes Pest discard the baseline

- **A change to a project file Pest fingerprints.** Pest counts these files only when git tracks them:
  - `composer.lock`
  - `phpunit.xml` and `phpunit.xml.dist`
  - `vite.config.ts`, `.js`, `.mjs`, `.cjs` and `.mts`
  - `package-lock.json`, `pnpm-lock.yaml`, `yarn.lock`, `bun.lock` and `bun.lockb`
  - `tsconfig.json`, `tsconfig.app.json` and `jsconfig.json`

  When one of them differs from the baseline, Pest discards the graph. With `--baselined` it downloads the latest baseline first, and when that one differs too, it records a new graph, which is the slow run again. A branch that changes dependencies pays this cost until the change reaches the default branch and CI publishes a new baseline.
- **A different PHP minor version than CI.** Pest keeps the graph but drops the test results recorded with it, so it runs those tests again instead of replaying their results.

## Rules

- Never commit the graph, `.pest/` or any other TIA cache. The graph changes on every run and is keyed to a branch, a commit and an environment. `vendor/bin/pest --baseline` prints where it is stored. When `pest()->tia()->directory()` puts it inside the project, keep that directory in `.gitignore`.
- A run with a coverage report option (`--coverage-clover` and similar) records no graph. Record with a plain `--tia` run, and coverage runs then reuse that graph. A partial run does not use TIA at all: a run with a test path, or with a selection option such as `--filter`, `--group`, `--testsuite`, `--exclude-testsuite`, `--covers`, `--uses` or `--dirty`.
- Edit `.github/workflows/tia-baseline.yml` above its marker line only. laravel-development-settings syncs everything below that line, and an edit there stops the file receiving updates. Put the repository's own test setup (services, PHP version and extensions, npm builds) in the optional hooks `.github/actions/tia-baseline-before-install` and `.github/actions/tia-baseline-after-install`. The workflow records on the default branch only, because Pest downloads from the latest successful run of that workflow on any branch.

## Repository secrets in the workflow

A composite hook cannot read the `secrets` context, and GitHub passes a secret to a run only when the workflow names it. To use the repository's own secrets, put a workflow-level `env:` block above the marker line. The sync keeps it byte for byte, and its variables exist in both hooks, in `composer install` and in the test run:

```yaml
env:
  COMPOSER_AUTH: '{"github-oauth": {"github.com": "${{ secrets.WORKFLOW_PAT }}"}}'
  NOVA_USERNAME: ${{ secrets.NOVA_USERNAME }}
  NOVA_LICENSE_KEY: ${{ secrets.NOVA_LICENSE_KEY }}
  SPARK_USERNAME: ${{ secrets.SPARK_USERNAME }}
  SPARK_API_TOKEN: ${{ secrets.SPARK_API_TOKEN }}
  FLUX_USERNAME: ${{ secrets.FLUX_USERNAME }}
  FLUX_LICENSE_KEY: ${{ secrets.FLUX_LICENSE_KEY }}
# mike-bronner/laravel-development-settings: project entries go above this line. Anything below it is lost on the next sync.
```

- Name each secret literally, as `${{ secrets.NAME }}`, with the names the repository defines. Never write `toJSON(secrets)` or an indexed secret such as `secrets[format(...)]`: CodeQL flags both.
- Add only top-level keys the workflow does not have, such as `env:`. A second `name:`, `on:` or `jobs:` makes the file invalid.
- Composer reads `COMPOSER_AUTH` on its own. Turn the other variables into Composer credentials in the before-install hook, for example `composer config http-basic.nova.laravel.com "$NOVA_USERNAME" "$NOVA_LICENSE_KEY"`.
