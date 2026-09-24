# Developer Settings

Shared developer settings, tooling configuration, and AI guidelines for Insight repositories.

## 📦 Installation

```bash
composer require mike-bronner/laravel-development-settings
```

That's it. The package automatically syncs files and manages dependencies on every `composer install` and `composer update`.

## 🔧 How It Works

This package is a Composer plugin that hooks into Composer's pre-update, post-install and post-update events. Before an update, it offers to contribute any edits you made to its installed guidelines and skills (see "Contributing edits made in vendor" below). After an install or update, it:

1. **Syncs tracked config files** (`pint.json`, `phpmd.xml`, …) from the package into your project
2. **Registers itself with Laravel Boost** by adding its name to the `packages` list in your `boost.json` (applications only — a package has no `artisan` and composes nothing)
3. **Installs or removes dev dependencies** as defined in the package config
4. **Preserves local modifications** — changed files aren't overwritten, and removed-upstream files you customized aren't deleted without asking
5. **Composes Laravel Boost** — apps run `php artisan boost:install --guidelines --skills --no-interaction`; packages have no `artisan` to run it with, so they are skipped. Composition is refused, by file and with the reason, when it would overwrite hand-written content (see "Why a run can refuse to compose" below). A run that composes nothing is reported as failed (see "Choosing your agents" below)
6. **Removes the legacy `.ai` symlink and `.dev-settings-boost` file** left by releases before the move to `resources/boost` (see "Upgrading" below)

### How the AI guidelines and skills reach your project

The shared guidelines and skills ship at `resources/boost/guidelines/` and `resources/boost/skills/`, which is Laravel Boost's own convention for a package. Boost finds them in vendor and composes them into your agent files (`CLAUDE.md`, `.claude/skills/`, …) alongside its own. Nothing is copied or symlinked into your project.

Two conditions have to hold, and both are enforced:

- **Boost only composes a package it is told about.** With no entry under `packages` in `boost.json`, Boost discovers the directories and then filters every one of them back out — zero guidelines, silently. Boost cannot add the entry itself during a Composer run, so the plugin writes it.
- **Boost 2.9 or newer.** Earlier releases keyed third-party guidelines by package name inside their per-file loop, so only the last of the four shipped files survived. This package declares a Composer conflict with `laravel/boost` below 2.9, so Composer refuses the combination instead of installing it. A project locked to an older Boost has to update it alongside this package: `composer update mike-bronner/laravel-development-settings laravel/boost`.

Your project is a **direct** dependency's consumer or it gets nothing: Boost excludes transitive dependencies by design, so a package that picks this one up indirectly receives no guidelines.

Composition itself needs `artisan`, so only full applications get composed agent files. A package consuming this one keeps the sources current in vendor, but nothing composes them — read `resources/boost` directly, or compose from the application that consumes the package.

### Choosing your agents

`boost.json` is gitignored, so a fresh clone has none. The plugin runs `boost:install` rather than `boost:update` for that reason: `install` writes the config it needs, where `update` finds guidelines and skills disabled and composes nothing.

Run non-interactively, `boost:install` composes for the agents `boost.json` names. When it names none, Boost picks the agents it detects on the machine (an agent's CLI on the `PATH`, its app installed) and in the project (its config directory or guideline file). It does not record that pick, so the plugin warns on every run until you choose:

```bash
php artisan boost:install
```

When Boost detects no agent at all, it exits successfully having written nothing. The plugin checks for a freshly composed agent file after the run, and reports the run as failed when there is none, rather than printing "done".

Boost also registers its commands only when `APP_ENV` is `local` or `APP_DEBUG` is true. On a clone with no `.env` yet, the run fails, and the next `composer install` after you create one composes.

### Why a run can refuse to compose

Boost writes its composed guidelines by replacing the region between an opening and a closing marker tag. The pattern is non-greedy and it anchors on the **first** opening tag anywhere in the file. So a hand-written section that names the opening tag in prose becomes the start of the match, the real block's closing tag becomes its end, and everything in between is replaced by generated content. This is a defect in Boost, not in your file. It has already cut one project's agent file from 299 lines to 125.

This package composes unattended, on every install and update, at a moment you did not choose. So it reads your markdown files first and stops before composing when one of them would be damaged:

- **Two or more opening tags.** The next composition replaces everything from the first tag to the nearest closing tag after it.
- **One opening tag with no closing tag after it.** This run would compose cleanly and append a real block, which leaves two opening tags behind. The run looks successful and arms the next one, so it is refused now.

The run names the file and the reason, and changes nothing. Fix the file yourself — remove or rephrase the prose mention, or close the tag — then run Composer again. The package will not repair it for you: where your own writing ends cannot be read from the file, and guessing wrong destroys the content the check exists to save.

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

### Output

After each `composer install` or `composer update`, you'll see a summary box showing what was created, updated, skipped (locally modified), or removed.

## 🛡️  Local Modification Protection

The package tracks known file checksums via a manifest. When syncing:

- **New files** are created automatically
- **Updated files** are overwritten only if your local copy matches a known version
- **Locally modified files** are skipped and flagged — your changes are preserved
- **Orphaned files** (removed from config) are cleaned up

To accept the package version of a locally modified file, delete your local copy and run `composer update`.

## ⚙️  Configuration

All behavior is driven by `config/development-settings.php` within the package. It defines:

- **`composer.install`** — dev dependencies to add to consuming projects
- **`composer.remove`** — deprecated dependencies to remove
- **`paths.directories`** — directories to sync (recursively)
- **`paths.files`** — individual files to sync
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
3. The plugin syncs files and dependencies automatically

### Upstream (Repos → Package)

1. A developer edits a tracked file in their project
2. On push to `main`, a GitHub Action detects changes to tracked files
3. A PR is automatically created on this repo
4. After human review and merge, a new release distributes the changes

The upstream workflow reads tracked paths directly from the package config — no hardcoded file lists to maintain. It reads both halves of each entry, so a file you edit at `.gitignore` goes back to the package as `resources/project/gitignore` rather than overwriting the package's own ignore rules.

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

## 📋 Manifest Management

The `manifest.json` tracks every known checksum of all managed files. It is how the plugin knows whether a local file was modified by you or matches a known version, and which removed-upstream files are safe to clean up. It is **append-only** (it retains entries for deleted files so downstream cleanup keeps working) and **generated** — never hand-edited.

When releasing a new version:

1. Update the source files (`config/development-settings.php` paths if adding/removing)
2. Run `composer dev-settings:manifest` to regenerate `manifest.json` and `capture-manifest.json`
3. Commit and tag a new release

`capture-manifest.json` is its sibling for the guideline and skill sources under `resources/boost`, keyed on package paths. It only feeds the edit check above. It is kept out of `manifest.json` on purpose: copy-sync and orphan cleanup read that file on project paths, and a `resources/boost/…` key there would let cleanup delete a consuming package's own `resources/boost` files. The same command generates both, append-only.

CI can guard against a stale manifest with `php bin/generate-manifest.php --check` (exits non-zero if regenerating either file would change anything).

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
