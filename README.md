# Developer Settings

Shared developer settings, tooling configuration, and AI guidelines for Insight repositories.

## 📦 Installation

```bash
composer require mike-bronner/laravel-development-settings
```

That's it. The package automatically syncs files on every `composer install` and `composer update`.

It also brings the shared tooling with it, as its own Composer requirements: Laravel Boost, Laravel Pint, Larastan and `mike-bronner/clean-code`. Composer installs them with the package, so you do not require them yourself. The plugin never edits your `composer.json` and never runs Composer. A project that an earlier release gave these packages as dev dependencies keeps them: nothing is removed.

The one exception is `mike-bronner/clean-code`, if you want its guidelines in your agent files. It ships them as Laravel Boost guidelines, and Boost composes the guidelines of a direct dependency only. Require it yourself:

```bash
composer require --dev mike-bronner/clean-code
```

Until your `composer.json` requires it (in `require` or `require-dev`), every `composer install` and `composer update` prints that command. Once it does, the plugin adds `mike-bronner/clean-code` to the `packages` list in `boost.json`, next to this package (see "How the AI guidelines and skills reach your project" below).

Orchestra Testbench is not among them. Releases 0.3.4 and 0.4.0 required it, so it reached every application, and there it broke `pest --parallel`: Pest skips its Laravel handler whenever Testbench's `TestCase` class exists, so every worker shares one database. Composer removes it from an application on the next update. A package requires Testbench itself (see "Packages" below).

If your application's own `composer.json` lists `orchestra/testbench`, `pest --parallel` stays broken for as long as Testbench is installed. Remove it with `composer remove --dev orchestra/testbench`, unless your application needs it for its own reasons.

## 🔧 How It Works

This package is a Composer plugin that hooks into Composer's pre-update, post-install and post-update events. Before an update, it offers to contribute any edits you made to its installed guidelines and skills (see "Contributing edits made in vendor" below). After an install or update, it:

1. **Syncs tracked config files** (`pint.json`, `phpcs.xml`, …) from the package into your project
2. **Registers itself with Laravel Boost** by adding its name to the `packages` list in your `boost.json` (wherever Boost can run: an application, or a package with Orchestra Testbench installed). It adds `mike-bronner/clean-code` too, when your `composer.json` requires it, and otherwise tells you to require it
3. **Preserves local modifications** — changed files aren't overwritten, and removed-upstream files you customized aren't deleted without asking
4. **Composes Laravel Boost** — an interactive `composer` run on a terminal runs `php artisan boost:install` on that terminal: Boost's own prompts choose the features, packages and agents, you see its output, and Boost saves your agents to `boost.json`. Any other run, CI and `--no-interaction` included, runs `php artisan boost:install --no-interaction` with `--guidelines --skills --mcp`, whatever your `boost.json` says, and shows only a one-line result; packages run the same command through Testbench, rooted at the repository (see "Packages" below). Composition is refused, by file and with the reason, when it would overwrite hand-written content (see "Why a run can refuse to compose" below). A run that composes nothing is reported as failed (see "Choosing your agents" below)
5. **Removes the legacy `.ai` symlink and `.dev-settings-boost` file** left by releases before the move to `resources/boost` (see "Upgrading" below)

### How the AI guidelines and skills reach your project

The shared guidelines and skills ship at `resources/boost/guidelines/` and `resources/boost/skills/`, which is Laravel Boost's own convention for a package. Boost finds them in vendor and composes them into your agent files (`AGENTS.md`, `.claude/skills/`, …) alongside its own. Nothing is copied or symlinked into your project.

Two conditions have to hold, and both are enforced:

- **Boost only composes a package it is told about.** With no entry under `packages` in `boost.json`, Boost discovers the directories and then filters every one of them back out — zero guidelines, silently. Boost cannot add the entry itself during a Composer run, so the plugin writes it.
- **Boost 2.9 or newer.** Earlier releases keyed third-party guidelines by package name inside their per-file loop, so only the last of the shipped guideline files survived. This package declares a Composer conflict with `laravel/boost` below 2.9, so Composer refuses the combination instead of installing it. A project locked to an older Boost has to update it alongside this package: `composer update mike-bronner/laravel-development-settings laravel/boost`.

Your project is a **direct** dependency's consumer or it gets nothing: Boost excludes transitive dependencies by design, so a package that picks this one up indirectly receives no guidelines.

The same rule applies to `mike-bronner/clean-code`, which this package requires. Installed only through this package, it is transitive, so Boost composes none of its guidelines. Require it directly, and the plugin lists it in `boost.json` on the next run. The plugin adds it only when it is direct, because Boost ignores a listed package that is not.

### Boost's PHP guideline

Boost's own `php/core` guideline tells agents to prefer PHPDoc blocks and to use array shape types in them. The shipped `laravel` skill says the opposite: no comments or docblocks unless asked. So this package replaces it. Its Laravel service provider, discovered automatically, adds `php` to `boost.guidelines.exclude`, and keeps any guidelines your app already excludes there. The shipped `05-php` guideline then carries every other rule of Boost's `php/core` unchanged, plus the no-docblocks rule.

The same provider sets `boost.agents.claude_code.guidelines_path` to `AGENTS.md` when your app sets no path of its own, so Boost composes Claude Code's guidelines into `AGENTS.md`, the file every other agent reads, rather than into `CLAUDE.md`. A path you set in `config/boost.php` is kept.

Nothing is written to your `.ai` directory or to `config/boost.php`. If you turn off package discovery for this package (`extra.laravel.dont-discover`), Boost composes its own `php/core` again. The plugin then reports the Boost run as failed, names the agent file, and says why.

### Packages

A package has no `artisan` of its own, and the plugin writes none. This package does not install Orchestra Testbench, so require it yourself: `composer require --dev orchestra/testbench`. When Testbench is installed (`vendor/bin/testbench`), `composer install` and `composer update` run Boost through `bin/rooted-testbench.php`, a script in this package that boots Testbench rooted at your repository. Boost then writes `boost.json`, the skills and the agent files into your repository, as in an app. Choose your agents by running Boost yourself, from the repository root:

```bash
php vendor/mike-bronner/laravel-development-settings/bin/rooted-testbench.php boost:install
```

The script sets `APP_BASE_PATH` to the repository, `APP_ENV` to `local` unless you set it, and `TESTBENCH_WORKING_PATH`. It creates `bootstrap/cache` and `storage/framework/views`, because Testbench cannot boot rooted without them. The shipped `.gitignore` ignores what Testbench writes there, and in `storage/logs`. It does not ignore Laravel's placeholder `.gitignore` files in those directories, which an application commits so they exist on a fresh clone.

Before each Boost run in a package, the plugin runs `package:discover` through the same script. Laravel reads your repository's `bootstrap/cache/packages.php` and builds it only when it is missing, so without this step a service provider installed after the first run would never load. The `testbench package:discover` in your own `post-autoload-dump` script does not help here: it is not rooted at your repository. A failed refresh is reported with its output, and Boost still runs.

**Boost's MCP server runs through the same script in a package.** Boost writes `php artisan boost:mcp` into `.mcp.json` and each agent's MCP config, and runs every tool as `php artisan boost:execute-tool`. With no `artisan`, neither works, so the plugin fixes both:

- After each Boost run, it points the `laravel-boost` server in every MCP config that run wrote at `vendor/mike-bronner/laravel-development-settings/bin/rooted-testbench.php boost:mcp`. It changes that one argument and nothing else in the file, other servers included. A config Boost did not write during the run is not touched.
- This package's service provider makes Boost run every tool call through the script too, when your repository has no `artisan`. In an app, Boost's own executor and configs stay as Boost wrote them.

The plugin then checks that tool calls run through the script, because the tool-call fix relies on Boost internals that are not public API. A failed check, or a config it could not change, is reported with its cause, and the run carries on.

Earlier releases wrote an `artisan` shim and a managed `.gitattributes` into packages. The plugin now removes a shim or `.gitattributes` it wrote and you did not change. One you edited, or a `.gitattributes` with your own rules below the sync marker, is kept and listed as removed upstream. Delete it yourself when you no longer need it. The shim is recognized by its `LARAVEL_DEVELOPMENT_SETTINGS_ARTISAN_SHIM` constant, by the marker comment of older releases, or by matching a version this package shipped. Any other `artisan` is an app's and is never touched.

A package without `vendor/bin/testbench` (it does not require Testbench, its install is incomplete, or it uses a custom Composer `bin-dir`) is not composed. The run says so, and names the command that installs Testbench.

### Choosing your agents

`boost.json` is gitignored, so a fresh clone has none. The plugin runs `boost:install` rather than `boost:update` for that reason: `install` writes the config it needs, where `update` finds guidelines and skills disabled and composes nothing.

Run non-interactively, `boost:install` composes for the agents `boost.json` names. When it names none, Boost picks the agents it detects on the machine (an agent's CLI on the `PATH`, its app installed) and in the project (its config directory or guideline file). It does not record that pick, so a non-interactive run warns every time until you choose. An interactive `composer install` or `composer update` on a terminal asks you and saves the answer. So does running Boost yourself:

```bash
php artisan boost:install
```

In a package, run it through the rooted Testbench instead (see "Packages" above).

An interactive run also shows Boost's list of third-party packages. If you untick this package there, Boost composes none of its guidelines or skills for that run, and the next `composer` run adds it back to `boost.json`.

When Boost detects no agent at all, it exits successfully having written nothing. The plugin checks for a freshly composed agent file after the run, and reports the run as failed when there is none, rather than printing "done".

In an application, Boost also registers its commands only when `APP_ENV` is `local` or `APP_DEBUG` is true. On a clone with no `.env` yet, the run fails, and the next `composer install` after you create one composes.

### Why a run can refuse to compose

Boost writes its composed guidelines by replacing the region between an opening and a closing marker tag. The pattern is non-greedy and it anchors on the **first** opening tag anywhere in the file. So a hand-written section that names the opening tag in prose becomes the start of the match, the real block's closing tag becomes its end, and everything in between is replaced by generated content. This is a defect in Boost, not in your file. It has already cut one project's agent file from 299 lines to 125.

This package composes unattended, on every install and update, at a moment you did not choose. So it reads your markdown files first and stops before composing when one of them would be damaged:

- **Two or more opening tags.** The next composition replaces everything from the first tag to the nearest closing tag after it.
- **One opening tag with no closing tag after it.** This run would compose cleanly and append a real block, which leaves two opening tags behind. The run looks successful and arms the next one, so it is refused now.

The run names the file and the reason, and changes nothing. Fix the file yourself — remove or rephrase the prose mention, or close the tag — then run Composer again. The package will not repair it for you: where your own writing ends cannot be read from the file, and guessing wrong destroys the content the check exists to save.

### AGENTS.md and CLAUDE.md

`AGENTS.md` is the agents' `CONTRIBUTING.md`: commit it, and keep your own hand-written prose in it, outside Boost's block. Claude Code reads `AGENTS.md` itself, but only when no `CLAUDE.md`, `.claude/CLAUDE.md` or `CLAUDE.local.md` exists. So this package deletes the project's root `CLAUDE.md` on every install and update, whatever it holds, and the run says so. If git tracks it, commit the deletion. The package never touches `.claude/CLAUDE.md` or `CLAUDE.local.md`, and either one makes Claude Code skip `AGENTS.md` again. A `CLAUDE.md` link is removed as a link, and a `CLAUDE.md` directory is left alone.

If your app points Claude Code's guidelines at `CLAUDE.md` in `config/boost.php`, Boost composes into it, and a `CLAUDE.md` composed during the run is kept.

### Your project's own guidelines

`.ai/` at your project root belongs to **your project**, and this package never writes there. Boost composes `.ai/guidelines/*.md` into the same generated block as the baseline arriving from vendor, so a project adds its own guidance simply by dropping a file in. `.ai/skills/` and `.ai/rules/` work the same way. Commit all of it — the shipped `.gitignore` deliberately does not ignore `.ai`.

### Upgrading from the symlink releases

Releases before this one delivered `.ai` as a symlink into `vendor/mikebronner/development-settings`. The first `composer install` or `composer update` on the new version removes that link, and reports it as `- .ai (stale symlink into vendor)`. Left in place the link would dangle, because the directory it points at is gone — and while it stands, `.ai` is a window into vendor that your project cannot write to.

A real `.ai` directory is never touched. Only a symlink resolving inside this package, or inside the vendor path it used under its old name, is removed.

The same run removes `.dev-settings-boost`, the fingerprint cache the old runner wrote into every project root, and reports it as `- .dev-settings-boost (stale Boost fingerprint)`. Only a file holding a bare fingerprint is removed. The old package runner's `bootstrap/cache`, `storage/framework` and `storage/logs` directories are not removed, because they may hold your own files. The shipped `.gitignore` keeps ignoring them.

### Upgrading from `mikebronner/development-settings`

The package was renamed from `mikebronner/development-settings` to `mike-bronner/laravel-development-settings` when its repository moved. Composer treats the two names as different packages, so the old requirement keeps installing the old releases until you swap it:

```bash
composer remove mikebronner/development-settings
composer require mike-bronner/laravel-development-settings
```

If your `composer.json` names the old repository under `repositories`, point it at `https://github.com/mike-bronner/laravel-development-settings` first.

The new package declares that it replaces `mikebronner/development-settings`. So if you add the new requirement and forget to remove the old one, Composer installs only the new package, and the two plugins never run side by side. Remove the old requirement anyway: a requirement on a name that nothing ships under is only confusing.

The first run under the new name cleans up after the old one. It replaces `mikebronner/development-settings` with the new name in the `packages` list of `boost.json`, and leaves every other entry alone. It also removes a symlink-era `.ai` link into `vendor/mikebronner/development-settings`, which dangles once Composer deletes that directory. A consumer's copy of `.github/workflows/sync-developer-settings.yml` is updated to call the reusable workflow at its new path, unless you modified it locally.

### When a run fails

A failure the plugin reports itself, such as a file it could not write or a failed Boost run, is listed in the summary, and the run carries on. An exception or error thrown while the plugin publishes stops only the plugin, not Composer. The plugin prints the cause (the error's class, message, file and line) and tells you to run the same command again to finish setup: `composer install` after an install, `composer update` after an update. Composer then runs your project's own `post-install-cmd` or `post-update-cmd` scripts, which an uncaught error would skip. Add `-v` to see the trace.

One known cause is an update that also updates this plugin. It was observed on an update straight from `mikebronner/development-settings` 0.2.0 to 0.5.1. Composer loads the new plugin during the run, but classes the old version had already loaded stay in memory, and the new code can call a method they lack. The next run starts fresh. If the same failure comes back on that run, it is a bug. Please report it with the printed cause.

When the `CI` environment variable holds any non-empty value, as it does on most CI systems, such a failure still fails the run. `false` and `0` count as set too. An empty `CI` counts as unset. A CI install comes from the lock file and updates nothing mid-run, so a failure there is a real bug and must not pass unnoticed.

### Output

After each `composer install` or `composer update`, you'll see a summary box showing what was created, updated, skipped (locally modified), or removed.

## 🛡️  Local Modification Protection

The package tracks known file checksums via a manifest. When syncing:

- **New files** are created automatically
- **Updated files** are overwritten only if your local copy matches a known version
- **Locally modified files** are skipped and flagged — your changes are preserved
- **Orphaned files** (removed from config) are cleaned up

To accept the package version of a locally modified file, delete your local copy and run `composer update`.

### Your own `.gitignore` rules

The shipped `.gitignore` ends with one marker line:

```gitignore
# mike-bronner/laravel-development-settings: project entries go below this line. Anything above it is lost on the next sync.
```

The package owns everything above that line and replaces it on every sync. Everything below it is yours: the sync never changes it, and the upstream workflow never proposes it. Put your own rules there. Because they come last, they win: `!GEMINI.md` below the marker keeps a hand-written `GEMINI.md` in git even though the shipped rules ignore it.

The same rule lets an old line below the marker undo the shipped block. Each run lists, by line number, every line below the marker that ignores something the shipped rules leave tracked on purpose (`.ai`, `AGENTS.md`, and Laravel's placeholder `.gitignore` files in `bootstrap/cache`, `storage/framework` and `storage/logs`), and every line that repeats a shipped rule. Those lines are yours, so they are never changed: delete the ones you did not mean to keep. A file that already tracks a path is not untracked by any of this. Run `git rm --cached` on it yourself.

- A `.gitignore` with no marker that is exactly a version this package shipped gets the marker automatically.
- A `.gitignore` with no marker and your own edits is left alone. An interactive `composer update` offers to add the marker (default no), and moves your whole file, unchanged, below it. Old shipped rules in it then sit below the marker, and each run lists the ones that override the shipped block. A non-interactive run only warns. Nothing is proposed upstream from it either way.
- A `.gitignore` holding the marker twice is not touched at all, because the sync cannot tell where your part starts. Keep one marker line and run `composer update` again.
- An edit above the marker is treated like any other local modification: flagged, kept unless you choose to overwrite it, and proposed upstream. Overwriting replaces only the part above the marker.
- If the package stops shipping a file with a marker, it is removed only when the part above the marker is a version this package shipped and nothing sits below it. With your own rules below the marker, it is kept, and an interactive `composer update` asks whether to delete it.

Which files work this way is set by `paths.managed` in the package config. `.github/workflows/tia-baseline.yml` works the other way round, with your part first: see [Your own env block](#your-own-env-block).

### PHP_CodeSniffer

The package ships `phpcs.xml`, which runs the `CleanCode` standard from `mike-bronner/clean-code` over the whole project. It skips `bootstrap/cache`, `node_modules`, `public`, `storage` and `vendor` at the project root. So `vendor/bin/phpcs` needs no arguments, in an application and in a package alike. Paths given on the command line replace the project root for that run.

`phpcs.xml` is a synced file like `pint.json`. An edited copy is kept as a local modification, and the upstream workflow proposes the edit to this package.

The shipped `pint.json` is tuned to write what `phpcs.xml` asks for: `new Foo` carries no empty parentheses unless a method or property is read from the new object (`new Foo()->bar()` from PHP 8.4, `(new Foo)->bar()` before it), and imports are grouped as classes, then functions, then constants. Pint keeps the empty parentheses only before `->` and `?->`. It fails the whole file on `new Foo()::BAR` and `new Foo()[0]`, and it rewrites `new Foo()()` to `new Foo()`, which drops the call. Write those three as `(new Foo)::BAR`, `(new Foo)[0]` and `(new Foo)()`. Pint keeps the line breaks of a multi-line argument list as written, so a method chain can hang from a call whose arguments span lines. Where the two disagreed, `phpcs.xml` won.

Pint does not place braces or fix the spacing of a class declaration, because its `braces_position` and `class_definition` fixers are off. That lets an empty class, interface, trait or enum stay as `{}` on the line that declares it. PHPCS still reports brace placement and class-declaration spacing, and `vendor/bin/phpcbf` fixes both, including the space in `new class ()` for an anonymous class.

Releases before 0.3.3 shipped `phpcs.xml` pointing at `.php-codesniffer/MikeBronner/ruleset.xml`, and 0.3.3 removed both. An unmodified old `phpcs.xml` is a known version, so the next update replaces it. The old ruleset is still removed when unmodified.

`CleanCode` is found by name only when the PHP_CodeSniffer installer plugin has run. Composer refuses to install this package until your `composer.json` decides on that plugin, and `false` leaves `CleanCode` unregistered. Allow it:

```json
"config": {
    "allow-plugins": {
        "dealerdirect/phpcodesniffer-composer-installer": true
    }
}
```

## ⚙️  Configuration

All behavior is driven by `config/development-settings.php` within the package. It defines:

- **`paths.directories`** — directories to sync (recursively)
- **`paths.files`** — individual files to sync
- **`paths.managed`** — tracked files the project shares with the package at one marker line, the project's part below it (`.gitignore`)
- **`paths.managed_project_first`** — tracked files the project shares with the package at one marker line, the project's part above it (`.github/workflows/tia-baseline.yml`)
- **`paths.legacy_symlinks`** — project-root symlinks from older releases, removed on upgrade
- **`paths.ignore`** — file and directory names excluded from discovery anywhere in the tree
- **`hooks`** — the Boost composition command and its progress label
- **`capture`** — package directories whose installed copies are checked for local edits before an update (`resources/boost`)

A tracked entry comes in two shapes. A plain one names a single path, which the package and your project both use:

```php
'files' => [
    'pint.json',
],
```

A keyed one reads **source => target**: the package ships the file on the left and your project receives it on the right.

```php
'files' => [
    'resources/project/gitignore' => '.gitignore',
],
```

That is how the ignore rules shipped to you stay separate from the package's own `.gitignore`, which is a different file with a different job. Both shapes work for `paths.directories` as well.

Nothing downstream changes with it: the manifest, the modification check and the orphan cleanup all key on the **target**, so a source can move or be renamed inside the package without your project seeing anything.

The shared guidelines and skills are **not** listed here. They ship at `resources/boost/` and Boost reads them out of vendor, so the plugin never copies or tracks them.

See the config file for the current values.

## 🔄 Bidirectional Sync

Changes flow both directions between this package and consuming repositories.

### Downstream (Package → Repos)

1. Changes are merged to this repo and a new version is tagged
2. Consumer repos run `composer update`
3. The plugin syncs files automatically, and Composer installs the tooling this package requires

### Upstream (Repos → Package)

1. A developer edits a tracked file in their project
2. On push to `main`, a GitHub Action detects changes to tracked files
3. A PR is automatically created on this repo
4. After human review and merge, a new release distributes the changes

The upstream workflow reads tracked paths directly from the package config — no hardcoded file lists to maintain. It reads both halves of each entry, so a file you edit at `.gitignore` goes back to the package as `resources/project/gitignore` rather than overwriting the package's own ignore rules. From `.gitignore` it takes only the part above the sync marker, so your own rules below it stay in your project.

This flow covers the **copied** config files only. Guidelines and skills are no longer copied into consuming projects, so a change to them is made here and released downstream; a guideline a project writes in its own `.ai/guidelines` stays that project's.

### Contributing edits made in vendor

The guidelines and skills are read out of `vendor/mike-bronner/laravel-development-settings/resources/boost`. A fix made there in place is lost at the next `composer update`, which replaces vendor, and no commit in your project carries it.

So before every `composer update`, the plugin compares each installed file under `resources/boost` with every version this package ever shipped. The check is local and uses checksums only. When a file matches none, the plugin lists it, and:

- **Interactively**, it asks whether to contribute the edits before updating. The default is no. Yes opens a pull request on this repository from a fresh clone.
- **Non-interactively**, it names the files and the command, and opens nothing.

Run `vendor/bin/dev-settings-contribute.php` (or `composer dev-settings:contribute`) to open the pull request yourself. It authenticates with `DEVELOPER_SETTINGS_TOKEN`, or with a `gh`-authenticated git.

### Setup

1. Create a GitHub Personal Access Token with `repo` scope
2. Add it as `DEVELOPER_SETTINGS_TOKEN` secret to your repository (or org-level)

### When to Use Each Flow

- **Edit in your repo** — quick fixes, typo corrections, rule tweaks discovered while coding
- **Direct PR to this repo** — major additions, new guidelines, structural changes

## 🎯 Pest TIA baseline

Pest 5's Test Impact Analysis (`--tia`) records a dependency graph on its first run, and that run is slow. Pest can download a graph that CI recorded instead (`--tia --baselined`). The plugin syncs the workflow that records and publishes that graph, `.github/workflows/tia-baseline.yml`, into every consuming project, as it syncs `pint.json`. The workflow calls the shared action in this repository, `.github/actions/tia-baseline`. The shipped guideline `resources/boost/guidelines/06-pest-tia.md` tells developers and agents how to use the baseline.

**Edit `tia-baseline.yml` above its marker line only.** The synced file starts with one marker line, and the package owns everything below it. Above it, you can add a workflow-level `env:` block (see [Your own env block](#your-own-env-block)). An edit below the marker is kept as locally modified, stops receiving updates, and is proposed back to this package. The workflow detects and sets up the usual Laravel test environment itself (see [What the workflow sets up](#what-the-workflow-sets-up)). Anything else a repository needs goes in two optional hooks and in the PHP version choice below. This package keeps the workflow's source at `resources/project/tia-baseline.yml`, outside `.github/workflows`, so this repository never runs it.

### What the workflow runs

1. Check out the repository.
2. Run the hook `.github/actions/tia-baseline-before-install`, when the repository has one.
3. Detect what the test run needs (see [What the workflow sets up](#what-the-workflow-sets-up)).
4. Start the database server and Redis, create the SQLite file, and copy `.env.example` to `.env`, each when the repository needs it.
5. Choose the PHP version.
6. Set up PHP with pcov, plus the extensions the before-install hook names, the extensions the database and Redis need, and the hook's ini values.
7. Run `composer install`.
8. Generate the application key, run `npm ci` and `npm run build`, install Playwright's Chromium, and make `storage` and `bootstrap/cache` writable, each when the repository needs it.
9. Run the hook `.github/actions/tia-baseline-after-install`, when the repository has one.
10. Run the shared action, which records the graph with `pest --tia --fresh` and uploads it.

A repository with no hooks and none of these needs runs steps 1, 3, 5, 6, 7 and 10 only.

### What the workflow sets up

The workflow reads each test setting the way the test run does. It takes the first of these that sets it:

1. A `<server>` entry in `phpunit.xml`, or in `phpunit.xml.dist` when there is no `phpunit.xml`.
2. The workflow's environment: your [env block](#your-own-env-block), and anything the before-install hook wrote to `$GITHUB_ENV`.
3. An `<env>` entry in the same file.
4. `.env.example`.

So a `<server>` entry beats your env block, as it does in the test run. To change a setting that `phpunit.xml` sets with `<server>`, change `phpunit.xml`.

The detection step runs on the PHP the runner has before `setup-php`, which every GitHub-hosted Ubuntu runner has. A self-hosted runner without PHP fails at that step.

| Step | Runs when | Default | Change it with |
|------|-----------|---------|----------------|
| Start PostgreSQL | `DB_CONNECTION` holds `pgsql` or `postgres`, such as `pgsql-testing` | `postgres:17` | `TIA_POSTGRES_VERSION` |
| Start MySQL | `DB_CONNECTION` holds `mysql` | `mysql:8.4` | `TIA_MYSQL_VERSION` |
| Start MariaDB | `DB_CONNECTION` holds `mariadb` | `mariadb:11` | `TIA_MARIADB_VERSION` |
| Create the SQLite file | `DB_CONNECTION` holds `sqlite`, and `DB_DATABASE` is not `:memory:` and names no file the repository holds | `database/database.sqlite` | `DB_DATABASE` |
| Start Redis | any one source sets a value to `redis`, or `phpunit.xml` or the workflow's environment names a `REDIS_` setting | `redis:7` | `TIA_REDIS_VERSION` |
| Copy `.env.example` to `.env` | `.env.example` exists and `.env` does not | | |
| Generate the application key | the repository has `artisan`, `.env.example` has an `APP_KEY` line, and no source sets a key | | |
| `npm ci` | `package.json` and `package-lock.json` exist | | |
| `npm run build` | as `npm ci`, and `package.json` has a `build` script | | |
| Install Playwright's Chromium | `composer.json` requires `pestphp/pest-plugin-browser`, and `package-lock.json` locks `playwright` | | |
| Make directories writable | `storage` or `bootstrap/cache` exists | | |

The database server gets its database, user and password from `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD`, and listens on `DB_PORT` at 127.0.0.1. When a setting is not set anywhere, the workflow uses `testing` for the database, `postgres` and `postgres` for PostgreSQL, and `root` and `password` for MySQL and MariaDB. PostgreSQL with an empty password trusts every connection, and MySQL or MariaDB as `root` with an empty password allows it. For MySQL or MariaDB with any other user, the workflow creates that user with the password, empty or not, and grants it every privilege, so `--parallel` can create its own databases. Redis listens on `REDIS_PORT` and requires `REDIS_PASSWORD` when one is set, and its health check uses that password. After a server is healthy, the workflow writes `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD`, or `REDIS_HOST` and `REDIS_PORT`, to `$GITHUB_ENV`, each one only when the environment does not set it already. So a Sail `.env.example` with `DB_HOST=pgsql` still reaches the server. The workflow also enables the PHP extension each server needs: `pdo_pgsql`, `pdo_mysql`, `pdo_sqlite`, or `redis` unless `REDIS_CLIENT` is `predis`.

The workflow starts no server on a port that is in use already, for example by a server the before-install hook started. `REDIS_` settings in `.env.example` alone start no Redis, because Laravel's default `.env.example` lists them while its drivers use the database. A driver set to `redis` in any source starts it, even when another source overrides that driver.

Playwright comes from the project's own npm dependency, so the browser matches the version Pest's plugin drives. When the project requires `pestphp/pest-plugin-browser` and `package-lock.json` locks no `playwright`, the run prints a warning and installs no browser. Run `npm install --save-dev playwright` and commit `package-lock.json` to fix it. When `package.json` has no `package-lock.json`, for example on pnpm or Yarn, the run prints a notice and runs no install and no build.

To change a default, set the variable in your env block above the marker line. `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` set there reach both the server and the test run:

```yaml
env:
  TIA_POSTGRES_VERSION: '16'
  TIA_REDIS_VERSION: '7.4'
  DB_DATABASE: app_testing
  DB_USERNAME: app
  DB_PASSWORD: secret
# mike-bronner/laravel-development-settings: project entries go above this line. Anything below it is lost on the next sync.
```

A `REDIS_` setting there, such as `REDIS_HOST: 127.0.0.1`, always starts Redis. Set one to start Redis when no driver uses it. Keep one `env:` block for these settings and your secrets. npm is the only package manager the workflow runs: a repository on pnpm, Yarn or Bun builds in the after-install hook.

The workflow runs on pushes to `main`, `master`, `develop` and `production`, and on a manual run (`workflow_dispatch`). The job runs only when the branch is the repository's default branch. On any other branch the job is skipped, and the run fails nothing. Pest downloads from the latest successful run of the workflow on any branch, so a run on another branch must never publish a graph. A repository whose default branch has another name records on a manual run only.

### The hooks

Each hook is a composite action that the repository owns: an `action.yml` in its own directory under `.github/actions`. The plugin never writes or reads them, so they never show as locally modified. Every `run` step in a composite action needs `shell: bash`.

| Hook | Runs | Use it for |
|------|------|------------|
| `tia-baseline-before-install` | before the workflow sets anything up | services the workflow does not start, environment variables, Composer credentials, the PHP choices below |
| `tia-baseline-after-install` | after `composer install` and the workflow's own setup | migrations, a build with another package manager, other browsers |

Both hooks receive one JSON input named `secrets`: the value of the repository secret `TIA_BASELINE_SECRETS`, or `{}` when the repository does not define it. Put the secrets your hooks need in `TIA_BASELINE_SECRETS` as one JSON object, such as `{"WORKFLOW_PAT": "..."}`. Declare the input, and read a secret with `fromJSON(inputs.secrets).NAME`. A composite action cannot read the `secrets` context itself, and the workflow does not pass the whole context, because CodeQL flags that as excessive secrets exposure. Before either hook runs, the workflow registers every line of every string or number in that object as a log mask, so no value shows in clear text in a later step's log. To use secrets your repository already has instead, name them in [your own env block](#your-own-env-block).

To set a variable for every later step, write it to `$GITHUB_ENV`. A composite action cannot declare `services:`, so start a service the workflow does not start with `docker run` instead.

The before-install hook can set these outputs. Each one is optional.

| Output | Default | Effect |
|--------|---------|--------|
| `php-version` | from `composer.json` | the PHP version the graph is recorded on |
| `php-extensions` | none | the `extensions` input of `shivammathur/setup-php`, before the extensions the workflow adds |
| `php-ini-values` | none | the `ini-values` input of `shivammathur/setup-php`, such as `memory_limit=-1, pcov.directory=.` |
| `pest-arguments` | none | the `arguments` input of the shared action, such as `--parallel` |

An example for an application that also needs Meilisearch, seeded data and Firefox:

```yaml
# .github/actions/tia-baseline-before-install/action.yml
name: TIA baseline setup before Composer
description: PHP choices, Meilisearch and Composer credentials for the TIA baseline.

inputs:
  secrets:
    description: The TIA_BASELINE_SECRETS object, as JSON.
    required: true

outputs:
  php-version:
    value: '8.5'
  php-extensions:
    value: imagick
  php-ini-values:
    value: memory_limit=-1, pcov.directory=.
  pest-arguments:
    value: --parallel

runs:
  using: composite
  steps:
    - name: Start Meilisearch
      shell: bash
      run: docker run --detach --name meilisearch --publish 7700:7700 getmeili/meilisearch:v1.15

    - name: Set the environment
      shell: bash
      env:
        COMPOSER_TOKEN: ${{ fromJSON(inputs.secrets).WORKFLOW_PAT }}
      run: |
        echo "SCOUT_DRIVER=meilisearch" >> "$GITHUB_ENV"
        echo "COMPOSER_AUTH={\"github-oauth\": {\"github.com\": \"${COMPOSER_TOKEN}\"}}" >> "$GITHUB_ENV"
```

```yaml
# .github/actions/tia-baseline-after-install/action.yml
name: TIA baseline setup after Composer
description: Seeded data and Firefox for the TIA baseline.

inputs:
  secrets:
    description: The TIA_BASELINE_SECRETS object, as JSON.
    required: true

runs:
  using: composite
  steps:
    - shell: bash
      run: php artisan db:seed --class=ReferenceDataSeeder --force

    - shell: bash
      run: npx playwright install --with-deps firefox
```

### Your own env block

A composite action cannot read the `secrets` context, and GitHub passes a secret to a run only when the workflow names it. To use the repository secrets you already have, without copying them into `TIA_BASELINE_SECRETS`, name them in a workflow-level `env:` block above the marker line of your synced `tia-baseline.yml`:

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

The sync keeps every line above the marker byte for byte and refreshes everything below it. It never reports those lines as a local modification, and the upstream workflow never proposes them. Use the secret names your repository defines.

The variables exist in every step of the job: in both hooks, in `composer install`, and in the test run. Composer reads `COMPOSER_AUTH` on its own. Turn the other variables into Composer credentials in the before-install hook:

```yaml
    - name: Composer credentials
      shell: bash
      run: |
        composer config http-basic.nova.laravel.com "$NOVA_USERNAME" "$NOVA_LICENSE_KEY"
        composer config http-basic.spark.laravel.com "$SPARK_USERNAME" "$SPARK_API_TOKEN"
        composer config http-basic.composer.fluxui.dev "$FLUX_USERNAME" "$FLUX_LICENSE_KEY"
```

Rules for the block:

- Name each secret literally, as `${{ secrets.NAME }}`. Never write `toJSON(secrets)` or an indexed secret such as `secrets[format(...)]`: CodeQL flags both as excessive secrets exposure.
- Add only top-level keys the shipped workflow does not have, such as `env:`. A second `name:`, `on:` or `jobs:` makes the file invalid.
- A synced copy with no marker gets one automatically when it is a version this package shipped. A copy with no marker and your own edits is kept and listed as locally modified, with no sync marker. The sync never moves it above a marker, because the whole old workflow there would repeat every top-level key. Delete it, run `composer update` to get the shipped file, then add your block above the marker.
- A copy that holds the marker twice is not touched. Keep one marker line and run `composer update` again.

### The PHP version

Pest's environment fingerprint holds the PHP minor version, and a baseline from another minor loses its recorded test results. So record on the minor version developers use. The workflow takes the first of these that names a version:

1. The `php-version` output of the before-install hook, used as written.
2. `config.platform.php` in `composer.json`, cut to its minor version.
3. `require.php` in `composer.json`: the first version the constraint names, such as `8.4` for `^8.4`. For `^8.4` or `>=8.4`, that is the lowest version the constraint allows.

When none of them names a version, the run fails and says so. Set `php-version` when developers use a newer version than the lowest one `composer.json` allows.

### Rules for the hooks

- **Pass no coverage report option, test path or partial-run option in `pest-arguments`.** Pest treats `--filter`, `--group`, `--testsuite`, `--exclude-testsuite`, `--covers`, `--uses`, `--dirty` and similar options as a partial run. Under any of them Pest records no graph. A repository whose CI writes coverage keeps doing that in its test job, and this workflow records with a plain run.
- **Do not turn off pcov.** The workflow sets it up, and Pest records nothing without a coverage driver.
- **Never commit the graph or `.pest/`.** The graph changes on every run.

The action fails on Pest below 5, without a coverage driver, on a failed test run, and when Pest writes no `graph.json`. The workflow reaches every consuming project, so a project still on Pest 3 or 4 gets a failed run on every push to its default branch until it requires Pest 5. The action never ends green without the artifact, because Pest downloads from the latest successful run. A developer whose `--baselined` run finds no artifact there gets either an error or a slow local recording, depending on the message `gh` returns. On success the action uploads the artifact `pest-tia-baseline` with `graph.json` at its root, which is the name and layout Pest downloads. The artifact expires after the repository's artifact retention period. A repository that goes longer than that without a push to its default branch needs a manual run.

### Moving from a hand-written workflow

In 0.6.0, each repository wrote its own `tia-baseline.yml` from this README. The sync does not know that copy. It keeps the copy and reports it as locally modified, with no sync marker. To move to the shipped workflow:

1. Move each setup step of your copy that runs before `composer install` into `.github/actions/tia-baseline-before-install/action.yml`. Leave out the database, Redis, `.env`, npm, Playwright and permission steps the workflow runs itself (see [What the workflow sets up](#what-the-workflow-sets-up)). Turn any other `services:` into a `docker run` step, and job-level `env:` into lines written to `$GITHUB_ENV`. Keep an `env:` that names secrets for [your own env block](#your-own-env-block).
2. Move the PHP version, the `extensions` and `ini-values` of `setup-php`, and the `arguments` of the action into the outputs of that hook.
3. Move each other step that runs after `composer install` into `.github/actions/tia-baseline-after-install/action.yml`.
4. Delete `.github/workflows/tia-baseline.yml`, then run `composer update`. The plugin writes the shipped workflow, with the marker line at its top. Put your env block above that line.
5. Close any pull request the upstream workflow opened for your copy.

## 📋 Manifest Management

The `manifest.json` tracks every known checksum of all managed files. It is how the plugin knows whether a local file was modified by you or matches a known version, and which removed-upstream files are safe to clean up. It is **append-only** (it retains entries for deleted files so downstream cleanup keeps working) and **generated** — never hand-edited.

When releasing a new version:

1. Update the source files (`config/development-settings.php` paths if adding/removing)
2. If Boost changed its `php/core` guideline, run `composer dev-settings:guideline` to regenerate `resources/boost/guidelines/05-php.blade.php` from the installed Boost
3. Run `composer dev-settings:manifest` to regenerate `manifest.json`, `capture-manifest.json` and `package-manifest.json`
4. Commit and tag a new release

`capture-manifest.json` is its sibling for the guideline and skill sources under `resources/boost`, keyed on package paths. It only feeds the edit check above. It is kept out of `manifest.json` on purpose: copy-sync and orphan cleanup read that file on project paths, and a `resources/boost/…` key there would let cleanup delete a consuming package's own `resources/boost` files. `package-manifest.json` holds the known versions of the files earlier releases wrote only into packages: the `artisan` shim and the `.gitattributes`. Nothing ships them now, so it changes no more, and the plugin reads it only in a package with Testbench, where orphan cleanup removes the unmodified copies. Kept in `manifest.json`, the `artisan` key would reach every app, where orphan cleanup would offer to delete the app's own `artisan`. The same command generates all three, append-only.

CI can guard against a stale manifest with `php bin/generate-manifest.php --check` (exits non-zero if regenerating any of the files would change anything). `php bin/generate-guideline.php --check` does the same for the PHP guideline against the installed Boost. It also fails when Boost's `php/core` no longer holds either PHPDoc line verbatim, and the generator then writes nothing. CI runs both on every pull request, on every push to `main`, and weekly, so a new Boost release is caught even when nothing here changed.

## 🧪 Local Development

To test changes before publishing:

```bash
# Add to your project's composer.json
{
    "repositories": [
        {
            "type": "path",
            "url": "../laravel-development-settings"
        }
    ]
}

# Require the local version
composer require mike-bronner/laravel-development-settings:@dev
```

The package is symlinked, so changes are reflected immediately.
