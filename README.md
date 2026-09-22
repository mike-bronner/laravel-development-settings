# Developer Settings

Shared developer settings, tooling configuration, and AI guidelines for Insight repositories.

## 📦 Installation

```bash
composer require mikebronner/development-settings
```

That's it. The package automatically syncs files and manages dependencies on every `composer install` and `composer update`.

## 🔧 How It Works

This package is a Composer plugin that hooks into Composer's post-install and post-update events. On each run, it:

1. **Syncs tracked config files** (`pint.json`, `phpmd.xml`, …) from the package into your project
2. **Registers itself with Laravel Boost** by adding its name to the `packages` list in your `boost.json` (applications only — a package has no `artisan` and composes nothing)
3. **Installs or removes dev dependencies** as defined in the package config
4. **Preserves local modifications** — changed files aren't overwritten, and removed-upstream files you customized aren't deleted without asking
5. **Composes Laravel Boost** — apps run `php artisan boost:update`; packages have no `artisan` to run it with, so they are skipped. Composition is refused, by file and with the reason, when it would overwrite hand-written content (see "Why a run can refuse to compose" below)
6. **Removes the legacy `.ai` symlink** left by releases before the move to `resources/boost` (see "Upgrading" below)

### How the AI guidelines and skills reach your project

The shared guidelines and skills ship at `resources/boost/guidelines/` and `resources/boost/skills/`, which is Laravel Boost's own convention for a package. Boost finds them in vendor and composes them into your agent files (`CLAUDE.md`, `.claude/skills/`, …) alongside its own. Nothing is copied or symlinked into your project.

Two conditions have to hold, and the plugin takes care of both:

- **Boost only composes a package it is told about.** With no entry under `packages` in `boost.json`, Boost discovers the directories and then filters every one of them back out — zero guidelines, silently. Boost cannot add the entry itself during a Composer run, so the plugin writes it.
- **Boost 2.9 or newer.** Earlier releases keyed third-party guidelines by package name inside their per-file loop, so only the last of the four shipped files survived.

Your project is a **direct** dependency's consumer or it gets nothing: Boost excludes transitive dependencies by design, so a package that picks this one up indirectly receives no guidelines.

Composition itself needs `artisan`, so only full applications get composed agent files. A package consuming this one keeps the sources current in vendor, but nothing composes them — read `resources/boost` directly, or compose from the application that consumes the package.

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

A real `.ai` directory is never touched. Only a symlink resolving inside this package is removed.

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

All behavior is driven by `config/developer-settings.php` within the package. It defines:

- **`composer.install`** — dev dependencies to add to consuming projects
- **`composer.remove`** — deprecated dependencies to remove
- **`paths.directories`** — directories to sync (recursively)
- **`paths.files`** — individual files to sync
- **`paths.legacy_symlinks`** — project-root symlinks from older releases, removed on upgrade
- **`paths.ignore`** — file and directory names excluded from discovery anywhere in the tree
- **`hooks`** — the Boost composition command and its progress label

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

### Setup

1. Create a GitHub Personal Access Token with `repo` scope
2. Add it as `DEVELOPER_SETTINGS_TOKEN` secret to your repository (or org-level)

### When to Use Each Flow

- **Edit in your repo** — quick fixes, typo corrections, rule tweaks discovered while coding
- **Direct PR to this repo** — major additions, new guidelines, structural changes

## 📋 Manifest Management

The `manifest.json` tracks every known checksum of all managed files. It is how the plugin knows whether a local file was modified by you or matches a known version, and which removed-upstream files are safe to clean up. It is **append-only** (it retains entries for deleted files so downstream cleanup keeps working) and **generated** — never hand-edited.

When releasing a new version:

1. Update the source files (`config/developer-settings.php` paths if adding/removing)
2. Run `composer dev-settings:manifest` to regenerate `manifest.json`
3. Commit and tag a new release

CI can guard against a stale manifest with `php bin/generate-manifest --check` (exits non-zero if regeneration would change anything).

## 🧪 Local Development

To test changes before publishing:

```bash
# Add to your project's composer.json
{
    "repositories": [
        {
            "type": "path",
            "url": "../developer-settings"
        }
    ]
}

# Require the local version
composer require mikebronner/development-settings:@dev
```

The package is symlinked, so changes are reflected immediately.
