# Developer Settings

Shared developer settings, tooling configuration, and AI guidelines for Insight repositories.

## 📦 Installation

```bash
composer require mikebronner/development-settings
```

That's it. The package automatically syncs files and manages dependencies on every `composer install` and `composer update`.

## 🔧 How It Works

This package is a Composer plugin that hooks into Composer's pre-update, post-install, and post-update events. On each run, it:

1. **Syncs tracked config files** (`pint.json`, `phpcs.xml`, …) from the package into your project
2. **Symlinks shared sources** (`.ai` guidelines/skills) at your project root, pointing into vendor — kept in sync without being committed or distributed
3. **Installs or removes dev dependencies** as defined in the package config
4. **Preserves local modifications** — changed files aren't overwritten, and removed-upstream files you customized aren't deleted without asking
5. **Composes Laravel Boost** — apps run `php artisan boost:update`; packages (no `artisan`) compose via a bundled Testbench-hosted runner when `orchestra/testbench` is present
6. **Offers upstream contribution** — before an update overwrites vendor, edits you made to the symlinked `.ai` are detected and offered as a PR back to this repo (or run `vendor/bin/dev-settings-contribute` any time)

### AI guidelines (`.ai`) in apps vs packages

`.ai` is the source Laravel Boost composes into your agent files (`CLAUDE.md`, `.claude/`, …). Because it's a gitignored symlink into vendor, it never bloats your package's git history or its distributed tarball — yet stays current with this package. Edit a guideline in-flow and `dev-settings-contribute` PRs it upstream.

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
- **`hooks`** — glob patterns and commands to run when matched files change

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

The upstream workflow reads tracked paths directly from the package config — no hardcoded file lists to maintain.

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
