# Pest Test Impact Analysis

Pest 5 can run only the tests a change affects (`--tia`). The first local `--tia` run records a dependency graph of the whole suite, and that run is slow. A repository with a `tia-baseline.yml` workflow publishes that graph from CI on every push to its default branch, so you can download it instead of recording it.

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
- Never edit `tia-baseline.yml` to run on other branches. Pest downloads from the latest successful run of that workflow on any branch.
