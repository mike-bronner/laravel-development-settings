#!/usr/bin/env bash
#
# Records a Pest Test Impact Analysis graph and stages it for upload as the
# pest-tia-baseline artifact. It runs in the consuming repository's root, after
# that repository's own workflow has set up its test environment.
#
# Every check below fails the step. Pest downloads from the latest successful
# run of the workflow, so a green run without the artifact would become that
# run. A developer's `--baselined` run would then either stop with an error or
# record the graph locally, the slow run, depending on the message gh gives.

set -euo pipefail

fail() {
    echo "::error title=Pest TIA baseline::$1"
    exit 1
}

# Under pull_request_target, GITHUB_REF names the base branch while the
# checkout can hold the pull request's code. So only events that run the
# default branch's own code may record: push, workflow_dispatch and schedule.
case "${GITHUB_EVENT_NAME:-}" in
    push | workflow_dispatch | schedule) ;;
    *) fail "This run was triggered by ${GITHUB_EVENT_NAME:-an unknown event}. Record the baseline on push, workflow_dispatch or schedule only." ;;
esac

# Pest finds the run with `gh run list --workflow tia-baseline.yml`, which does
# not filter by branch. A run on any other branch would publish its graph as
# the baseline, so recording runs on the default branch only.
default_branch=$(php <<'PHP'
<?php
$path = getenv('GITHUB_EVENT_PATH');
$event = is_string($path) && is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
$branch = is_array($event) && is_array($event['repository'] ?? null) ? ($event['repository']['default_branch'] ?? null) : null;
echo is_string($branch) ? $branch : '';
PHP
)

if [ -z "$default_branch" ]; then
    fail "The event payload names no default branch, so this run cannot tell whether it may publish a baseline."
fi

if [ "${GITHUB_REF:-}" != "refs/heads/${default_branch}" ]; then
    fail "This run is on ${GITHUB_REF:-an unknown ref}. Record the baseline on pushes to ${default_branch} only: Pest downloads the latest successful run on any branch."
fi

if [ ! -x vendor/bin/pest ]; then
    fail "vendor/bin/pest is missing. Install the Composer dependencies before this step."
fi

version_output=$(vendor/bin/pest --version) || fail "vendor/bin/pest --version failed."
version=$(printf '%s\n' "$version_output" | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' | head -n 1 || true)

if [ -z "$version" ]; then
    fail "Could not read the Pest version from: ${version_output}"
fi

if [ "${version%%.*}" -lt 5 ]; then
    fail "Pest ${version} has no Test Impact Analysis. Require Pest 5 or later before recording a baseline."
fi

# The same test Pest makes before it records (Recorder::driverAvailable()).
# Without a driver Pest runs the suite, records nothing and still exits 0.
driver=$(php <<'PHP'
<?php
if (function_exists('pcov\start') && filter_var((string) ini_get('pcov.enabled'), FILTER_VALIDATE_BOOL)) {
    echo 'pcov';
} elseif (function_exists('xdebug_start_code_coverage') && function_exists('xdebug_info') && in_array('coverage', (array) xdebug_info('mode'), true)) {
    echo 'xdebug';
}
PHP
)

if [ -z "$driver" ]; then
    fail "No coverage driver. Pest records the graph through pcov, or Xdebug in coverage mode. Set one up, for example with coverage: pcov in shivammathur/setup-php."
fi

# `--baseline` prints the directory the graph is written to, after Pest has
# read tests/Pest.php, so a `pest()->tia()->directory()` setting is honored.
storage=$(vendor/bin/pest --baseline | tail -n 1) || fail "vendor/bin/pest --baseline failed."

if [ -z "$storage" ]; then
    fail "Pest printed no storage directory for --baseline."
fi

rm -f "${storage}/graph.json"

read -r -a arguments <<< "${TIA_BASELINE_ARGUMENTS:-}"

echo "Recording the Pest TIA graph with ${driver} (Pest ${version})."

if ! vendor/bin/pest --tia --fresh ${arguments[@]+"${arguments[@]}"}; then
    fail "The test run failed, so no baseline is published. The last successful baseline stays in use."
fi

if [ ! -s "${storage}/graph.json" ]; then
    fail "Pest wrote no graph.json to ${storage}. A coverage report option, a test path, or a selection option such as --filter, --group, --testsuite or --dirty in the arguments stops Pest from recording, and so does a run that records no edges."
fi

artifact="${RUNNER_TEMP:?RUNNER_TEMP is not set}/pest-tia-baseline"
mkdir -p "$artifact"
cp "${storage}/graph.json" "${artifact}/graph.json"

echo "path=${artifact}" >> "${GITHUB_OUTPUT:?GITHUB_OUTPUT is not set}"
