<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;

use Laravel\Prompts\Prompt;
use MikeBronner\DevelopmentSettings\Support\BoostRegistrar;
use MikeBronner\DevelopmentSettings\Support\CheckedFile;
use MikeBronner\DevelopmentSettings\Support\ContributionDetector;
use MikeBronner\DevelopmentSettings\Support\Contributor;
use MikeBronner\DevelopmentSettings\Support\FileDiscovery;
use MikeBronner\DevelopmentSettings\Support\FileSync;
use MikeBronner\DevelopmentSettings\Support\GuidelineGuard;
use MikeBronner\DevelopmentSettings\Support\LegacyFingerprint;
use MikeBronner\DevelopmentSettings\Support\LegacySymlink;
use MikeBronner\DevelopmentSettings\Support\Manifest;
use MikeBronner\DevelopmentSettings\Support\SystemProcess;
use MikeBronner\DevelopmentSettings\Support\TestbenchMcp;
use RuntimeException;

final class ComposerPlugin implements EventSubscriberInterface, PluginInterface
{
    private const PACKAGE_NAME = 'mike-bronner/laravel-development-settings';

    /**
     * The name this package shipped under before the repository moved. An
     * upgraded project can still hold it in `boost.json` and in a symlink-era
     * `.ai` link, and only this package can clean those up.
     */
    private const LEGACY_PACKAGE_NAME = 'mikebronner/development-settings';
    private const MANIFEST_FILE = 'manifest.json';
    private const BOX_WIDTH = 80;
    private const AFTER_ROOT_SCRIPTS = -1;

    private const BOOST_FEATURES = ' --guidelines --skills --mcp';
    private const PACKAGE_COMMAND = 'php -d variables_order=EGPCS ' . TestbenchMcp::TESTBENCH . ' boost:install --no-interaction';
    private const PACKAGE_BOOST_INSTALL = 'APP_BASE_PATH=. APP_ENV=local php -d variables_order=EGPCS ' . TestbenchMcp::TESTBENCH . ' boost:install';

    // Without the views directory a rooted Testbench exits successfully having
    // composed no guidelines.
    private const TESTBENCH_DIRECTORIES = ['bootstrap/cache', 'storage/framework/views'];

    private static bool $dependenciesInjected = false;

    public function activate(Composer $composer, IOInterface $io): void {}

    public function deactivate(Composer $composer, IOInterface $io): void {}

    public function uninstall(Composer $composer, IOInterface $io): void {}

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::PRE_UPDATE_CMD => 'captureBeforeUpdate',
            ScriptEvents::POST_INSTALL_CMD => [['publish', 0], ['routeMcpThroughTestbench', self::AFTER_ROOT_SCRIPTS]],
            ScriptEvents::POST_UPDATE_CMD => [['publish', 0], ['routeMcpThroughTestbench', self::AFTER_ROOT_SCRIPTS]],
        ];
    }

    public function routeMcpThroughTestbench(Event $event): void
    {
        $this->doRouteMcpThroughTestbench($event->getIO());
    }

    public function publish(Event $event): void
    {
        $this->doPublish($event->getIO());
    }

    public function captureBeforeUpdate(Event $event): void
    {
        $this->doCapture($event->getIO());
    }

    private function doCapture(IOInterface $io): void
    {
        $packageDir = $this->getPackageDir();

        if (! $packageDir) {
            return;
        }

        $projectDir = getcwd();
        $config = require $packageDir . '/config/development-settings.php';
        $modified = (new ContributionDetector)->modified(
            packageDir: $packageDir,
            directories: $config['capture'] ?? [],
            sources: Manifest::load($packageDir . '/' . ContributionDetector::MANIFEST_FILE),
            ignore: $config['paths']['ignore'] ?? FileDiscovery::DEFAULT_IGNORE,
        );

        if ($modified === []) {
            return;
        }

        $io->write('');
        $io->write(sprintf('<comment>You have %d local edit(s) to shared development-settings files:</comment>', count($modified)));

        foreach (array_keys($modified) as $path) {
            $io->write('  <comment>· ' . $path . '</comment>');
        }

        if (! $io->isInteractive()) {
            $io->writeError('<comment>  These live in vendor and will be lost on update. Run "vendor/bin/dev-settings-contribute.php" to PR them upstream.</comment>');

            return;
        }

        Prompt::interactive(true);

        if (! confirm(label: 'Contribute these to development-settings before updating?', default: false)) {
            $io->write('<comment>  Skipped — run "vendor/bin/dev-settings-contribute.php" later to contribute.</comment>');

            return;
        }

        $result = (new Contributor(new SystemProcess))->open(
            modified: $modified,
            branch: $this->contributionBranch($projectDir),
            cloneDir: sys_get_temp_dir() . '/devset-contribute-' . bin2hex(random_bytes(5)),
            token: getenv('DEVELOPER_SETTINGS_TOKEN') ?: null,
        );

        $io->write($result['status'] === 0
            ? '<info>  ' . $result['message'] . '</info>'
            : '<error>  ' . $result['message'] . '</error>');
    }

    private function doRouteMcpThroughTestbench(IOInterface $io): void
    {
        $projectDir = (string) getcwd();

        if (! $this->getPackageDir() || $this->isApp($projectDir)) {
            return;
        }

        $result = (new TestbenchMcp)->rewrite($projectDir);

        foreach ($result['rewritten'] as $path) {
            $io->write(sprintf('<info>  ↻ %s now runs the Boost MCP server through %s.</info>', $path, TestbenchMcp::TESTBENCH));
        }

        foreach ($result['skipped'] as $path => $reason) {
            $io->writeError(sprintf(
                '<comment>  %s holds a Boost MCP entry that was not pointed at %s: %s.</comment>',
                $path,
                TestbenchMcp::TESTBENCH,
                rtrim($reason, '.'),
            ));
        }
    }

    private function contributionBranch(string $projectDir): string
    {
        $slug = preg_replace('/[^a-z0-9._-]+/i', '-', basename($projectDir)) ?? 'project';

        return 'contribute/' . $slug . '-' . date('YmdHis');
    }

    private function doPublish(IOInterface $io): void
    {
        $packageDir = $this->getPackageDir();

        if (! $packageDir) {
            // Running inside development-settings itself: nothing to publish.
            if (! $this->isRunningInOwnRepository()) {
                $io->writeError('<error>Could not locate the ' . self::PACKAGE_NAME . ' package directory</error>');
            }

            return;
        }

        $projectDir = getcwd();
        $config = require $packageDir . '/config/development-settings.php';
        $manifest = Manifest::load($packageDir . '/' . self::MANIFEST_FILE);

        $filesToPublish = (new FileDiscovery)->discover(
            packageDir: $packageDir,
            paths: $config['paths'],
            ignore: $config['paths']['ignore'] ?? FileDiscovery::DEFAULT_IGNORE,
        );

        // Remove the legacy `.ai` link before anything inspects the project
        // tree: while it stands, every `.ai/…` manifest path resolves into
        // vendor and orphan cleanup would delete this package's own sources.
        $removedLinks = (new LegacySymlink)->remove(
            projectDir: $projectDir,
            packageDirs: [$packageDir, $projectDir . '/vendor/' . self::LEGACY_PACKAGE_NAME],
            linkPaths: $config['paths']['legacy_symlinks'] ?? [],
        );
        $removedFingerprint = (new LegacyFingerprint)->remove($projectDir);

        $fileSync = new FileSync($manifest, managed: $config['paths']['managed'] ?? []);
        $scan = $fileSync->classify($projectDir, $filesToPublish);
        $safeOrphans = $fileSync->safeOrphans($projectDir, $filesToPublish);
        $protectedOrphans = $fileSync->protectedOrphans($projectDir, $filesToPublish);

        // Only where Boost can actually compose. A package without Testbench has
        // no way to run Boost, so registering there would write a config file
        // nothing ever reads.
        $registration = $this->composesBoost($projectDir)
            ? (new BoostRegistrar)->register(
                projectDir: $projectDir,
                package: self::PACKAGE_NAME,
                replaces: [self::LEGACY_PACKAGE_NAME],
            )
            : null;

        $composerConfig = $config['composer'] ?? [];
        $dependencyResult = $this->prepareComposerDependencies(
            install: $composerConfig['install'] ?? [],
            remove: $composerConfig['remove'] ?? [],
        );

        // New and known-version files need no answer from the user, so they are
        // written before the summary lists them: a write that fails is listed
        // as failed, never as created or updated. As with a failed Boost run,
        // the failure is reported with its cause and the run carries on.
        $failedWrites = [];

        foreach (['new', 'updatable'] as $group) {
            foreach ($scan[$group] as $path => $sourceFile) {
                $failure = $this->writeTracked($fileSync, $projectDir, $path, $sourceFile);

                if ($failure !== null) {
                    unset($scan[$group][$path]);
                    $failedWrites[$path] = $failure;
                }
            }
        }

        $this->writeBoxHeader($io);

        foreach (array_keys($failedWrites) as $path) {
            $io->write($this->formatOutputLine(type: 'failed', path: $path));
        }

        foreach (array_keys($scan['new']) as $path) {
            $io->write($this->formatOutputLine(type: 'created', path: $path));
        }

        foreach (array_keys($scan['updatable']) as $path) {
            $io->write($this->formatOutputLine(type: 'updated', path: $path));
        }

        foreach (array_keys($scan['modified']) as $path) {
            $io->write($this->formatOutputLine(type: 'modified', path: $path));
        }

        foreach (array_keys($scan['unmarked']) as $path) {
            $io->write($this->formatOutputLine(type: 'unmarked', path: $path));
        }

        foreach (array_keys($scan['refused']) as $path) {
            $io->write($this->formatOutputLine(type: 'refused', path: $path));
        }

        foreach ($safeOrphans as $path) {
            $io->write($this->formatOutputLine(type: 'removed', path: $path));
        }

        foreach ($protectedOrphans as $path) {
            $io->write($this->formatOutputLine(type: 'orphan_protected', path: $path));
        }

        foreach (array_keys($dependencyResult['toInstall']) as $package) {
            $io->write($this->formatOutputLine(type: 'dep_added', path: $package));
        }

        foreach ($dependencyResult['toRemove'] as $package) {
            $io->write($this->formatOutputLine(type: 'dep_removed', path: $package));
        }

        foreach ($removedLinks as $linkPath) {
            $io->write($this->formatOutputLine(type: 'unlinked', path: $linkPath));
        }

        if ($removedFingerprint) {
            $io->write($this->formatOutputLine(type: 'stale_fingerprint', path: LegacyFingerprint::FILE));
        }

        if ($registration === BoostRegistrar::REGISTERED) {
            $io->write($this->formatOutputLine(type: 'registered', path: BoostRegistrar::FILE));
        }

        $filesToOverwrite = [];

        if ($scan['modified'] !== []) {
            Prompt::interactive($io->isInteractive());

            $filesToOverwrite = multiselect(
                label: 'Overwrite locally modified files?',
                options: array_combine(
                    array_keys($scan['modified']),
                    array_keys($scan['modified']),
                ),
                default: [],
                required: false,
                hint: 'Space to toggle, Enter to confirm.',
            );
        }

        // An edited file with no sync marker cannot be split into the package's
        // part and the project's, so it is only converted on consent. The
        // conversion loses nothing: the whole file moves below the marker.
        $filesToConvert = [];

        if ($scan['unmarked'] !== []) {
            if ($io->isInteractive()) {
                Prompt::interactive(true);

                $filesToConvert = multiselect(
                    label: 'Add the sync marker to these locally modified files?',
                    options: array_combine(array_keys($scan['unmarked']), array_keys($scan['unmarked'])),
                    default: [],
                    required: false,
                    hint: 'Your whole file moves below the marker. Unselected files are kept as they are.',
                );
            } else {
                $io->writeError(sprintf(
                    '<comment>  %d locally-modified file(s) have no sync marker and were not updated. Run composer interactively to add it; your entries are kept below it.</comment>',
                    count($scan['unmarked']),
                ));
            }
        }

        foreach (array_keys($scan['refused']) as $path) {
            $io->writeError(sprintf(
                '<error>  %s holds the sync marker more than once, so it was not touched. Keep one marker line and run composer again.</error>',
                $path,
            ));
        }

        $orphansToDelete = [];

        if ($protectedOrphans !== []) {
            if ($io->isInteractive()) {
                Prompt::interactive(true);

                $orphansToDelete = multiselect(
                    label: 'Delete files removed upstream that you have modified locally?',
                    options: array_combine($protectedOrphans, $protectedOrphans),
                    default: [],
                    required: false,
                    hint: 'Unselected files are kept. Space to toggle, Enter to confirm.',
                );
            } else {
                $io->writeError(sprintf(
                    '<comment>  %d locally-modified file(s) removed upstream were kept. Delete manually if no longer needed.</comment>',
                    count($protectedOrphans),
                ));
            }
        }

        $stats = [
            'new' => count($scan['new']),
            'updated' => count($scan['updatable']),
            'unchanged' => count($scan['unchanged']),
            'skipped' => count($scan['refused']) + count($failedWrites),
            'removed' => 0,
        ];

        $consented = [
            ...array_intersect_key($scan['modified'], array_flip($filesToOverwrite)),
            ...array_intersect_key($scan['unmarked'], array_flip($filesToConvert)),
        ];

        foreach ([...$scan['modified'], ...$scan['unmarked']] as $path => $sourceFile) {
            if (array_key_exists($path, $consented)) {
                $failure = $this->writeTracked($fileSync, $projectDir, $path, $sourceFile);

                if ($failure === null) {
                    $stats['updated']++;

                    continue;
                }

                $failedWrites[$path] = $failure;
            }

            $stats['skipped']++;
        }

        foreach ($failedWrites as $path => $failure) {
            $io->writeError(sprintf(
                '<error>  %s was not updated. %s Fix the cause, then run the Composer command again.</error>',
                $path,
                rtrim($failure, '.') . '.',
            ));
        }

        foreach ($safeOrphans as $orphanPath) {
            $this->deleteOrphan($projectDir, $orphanPath);
            $stats['removed']++;
        }

        foreach ($protectedOrphans as $orphanPath) {
            if (! in_array($orphanPath, $orphansToDelete, true)) {
                $stats['skipped']++;

                continue;
            }

            $this->deleteOrphan($projectDir, $orphanPath);
            $stats['removed']++;
        }

        $stats['removed'] += count($removedLinks) + (int) $removedFingerprint;
        $stats['new'] += count($dependencyResult['toInstall']);
        $stats['removed'] += count($dependencyResult['toRemove']);
        $stats['unchanged'] += count($dependencyResult['unchanged']);

        match ($registration) {
            BoostRegistrar::REGISTERED => $stats['new']++,
            BoostRegistrar::UNCHANGED => $stats['unchanged']++,
            BoostRegistrar::UNREADABLE => $stats['skipped']++,
            default => null,
        };

        $this->writeBoxFooter($io, $stats);

        // Boost is not run over a file this package cannot read: `boost:install`
        // treats it as empty and writes a fresh config over it, destroying the
        // developer's agent, guideline and MCP settings.
        if ($registration === BoostRegistrar::UNREADABLE) {
            $io->writeError(sprintf(
                '<error>  %s is not valid JSON, so Laravel Boost was not run. Its guidelines and skills will not compose until you fix or delete the file.</error>',
                BoostRegistrar::FILE,
            ));
        } else {
            $this->runBoost($io, $projectDir, $config);
        }

        $this->installDevDependencies($io, $dependencyResult['toInstall']);
        $this->removeDevDependencies($io, $dependencyResult['toRemove']);
    }

    private function writeBoxHeader(IOInterface $io): void
    {
        $border = 'fg=gray';

        $io->write('');
        $io->write("<{$border}>┌" . str_repeat('─', self::BOX_WIDTH - 2) . '┐</>');
        $io->write("<{$border}>│</>  <fg=cyan>Developer Settings</>" . str_repeat(' ', self::BOX_WIDTH - 24) . "  <{$border}>│</>");
        $io->write("<{$border}>├" . str_repeat('─', self::BOX_WIDTH - 2) . '┤</>');
    }

    private function writeBoxFooter(IOInterface $io, array $stats): void
    {
        $border = 'fg=gray';

        $io->write("<{$border}>├" . str_repeat('─', self::BOX_WIDTH - 2) . '┤</>');

        $summaryParts = [
            $this->formatSummaryItem($stats['new'], 'new', 'green'),
            $this->formatSummaryItem($stats['updated'], 'updated', 'yellow'),
            $this->formatSummaryItem($stats['unchanged'], 'unchanged', 'gray', 'white'),
            $this->formatSummaryItem($stats['skipped'], 'skipped', 'red'),
            $this->formatSummaryItem($stats['removed'], 'removed', 'magenta'),
        ];

        $summary = implode(' · ', $summaryParts);
        $summaryPlain = preg_replace('/<[^>]+>/', '', $summary);
        $padding = self::BOX_WIDTH - 6 - mb_strlen($summaryPlain);
        $io->write("<{$border}>│</>  " . $summary . str_repeat(' ', $padding) . "  <{$border}>│</>");

        $io->write("<{$border}>└" . str_repeat('─', self::BOX_WIDTH - 2) . '┘</>');
        $io->write('');
    }

    private function formatSummaryItem(int $count, string $label, string $bgColor, ?string $fgColor = null): string
    {
        if ($count === 0) {
            return "<fg=gray>{$count} {$label}</>";
        }

        $fgColor ??= "bright-{$bgColor}";

        return "<fg={$fgColor};bg={$bgColor}> {$count} {$label} </>";
    }

    private function formatOutputLine(string $type, string $path): string
    {
        $formats = [
            'created' => ['icon' => '+', 'style' => 'info'],
            'updated' => ['icon' => '↻', 'style' => 'comment'],
            'modified' => ['icon' => '⚠', 'style' => 'fg=yellow'],
            'orphan_protected' => ['icon' => '⚠', 'style' => 'fg=yellow'],
            'unmarked' => ['icon' => '⚠', 'style' => 'fg=yellow'],
            'refused' => ['icon' => '⚠', 'style' => 'fg=red'],
            'failed' => ['icon' => '✗', 'style' => 'fg=red', 'suffix' => ' (write failed)'],
            'unlinked' => ['icon' => '-', 'style' => 'fg=magenta', 'suffix' => ' (stale symlink into vendor)'],
            'stale_fingerprint' => ['icon' => '-', 'style' => 'fg=magenta', 'suffix' => ' (stale Boost fingerprint)'],
            'registered' => ['icon' => '+', 'style' => 'info', 'suffix' => ' (registered with Boost)'],
            'removed' => ['icon' => '-', 'style' => 'fg=magenta'],
            'dep_added' => ['icon' => '+', 'style' => 'info', 'suffix' => ' (composer)'],
            'dep_removed' => ['icon' => '-', 'style' => 'fg=magenta', 'suffix' => ' (composer)'],
        ];

        $format = $formats[$type] ?? ['icon' => ' ', 'style' => null, 'suffix' => ''];
        $style = $format['style'] ?? null;
        $prefix = $style !== null
            ? "<{$style}>{$format['icon']}</{$style}>"
            : $format['icon'];
        $suffix = $format['suffix'] ?? '';

        $displayPath = match ($type) {
            'modified' => "{$path} (locally modified)",
            'unmarked' => "{$path} (locally modified, no sync marker)",
            'refused' => "{$path} (sync marker appears twice, not touched)",
            'orphan_protected' => "{$path} (removed upstream, kept — locally modified)",
            default => $path . $suffix,
        };

        $prefixLength = 1;
        $maxPathLength = self::BOX_WIDTH - 6 - $prefixLength - 2 - 1;

        if (strlen($displayPath) > $maxPathLength) {
            $displayPath = substr($displayPath, 0, $maxPathLength - 3) . '...';
        }

        $visibleLength = $prefixLength + 2 + strlen($displayPath);
        $padding = max(1, self::BOX_WIDTH - 6 - $visibleLength);

        return '<fg=gray>│</>  ' . $prefix . '  ' . $displayPath . str_repeat(' ', $padding) . '  <fg=gray>│</>';
    }

    private function prepareComposerDependencies(array $install, array $remove): array
    {
        if (self::$dependenciesInjected) {
            return ['toInstall' => [], 'unchanged' => [], 'toRemove' => []];
        }

        $composerFile = getcwd() . '/composer.json';
        $composerJson = json_decode(file_get_contents($composerFile), associative: true);
        $requireDev = $composerJson['require-dev'] ?? [];

        $packagesToInstall = [];
        $packagesUnchanged = [];
        $packagesToRemove = [];

        foreach ($install as $package => $version) {
            if (isset($requireDev[$package])) {
                $packagesUnchanged[$package] = $version;

                continue;
            }

            $packagesToInstall[$package] = $version;
            $requireDev[$package] = $version;
        }

        foreach ($remove as $package) {
            if (! isset($requireDev[$package])) {
                continue;
            }

            $packagesToRemove[] = $package;
            unset($requireDev[$package]);
        }

        if ($packagesToInstall !== [] || $packagesToRemove !== []) {
            self::$dependenciesInjected = true;

            ksort($requireDev);
            $composerJson['require-dev'] = $requireDev;
            file_put_contents(
                $composerFile,
                json_encode($composerJson, flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
            );
        }

        return ['toInstall' => $packagesToInstall, 'unchanged' => $packagesUnchanged, 'toRemove' => $packagesToRemove];
    }

    private function installDevDependencies(IOInterface $io, array $packages): void
    {
        if ($packages === []) {
            return;
        }

        $packageNames = implode(' ', array_keys($packages));
        $result = $this->executeCommand("composer update {$packageNames} --dev --no-interaction");

        if ($result === 0) {
            $io->write('<info>  + Dev dependencies installed successfully.</info>');
        } else {
            $io->writeError('<error>  + Failed to install dev dependencies. Run "composer update" manually.</error>');
        }

        $io->write('');
    }

    private function removeDevDependencies(IOInterface $io, array $packages): void
    {
        if ($packages === []) {
            return;
        }

        $packageNames = implode(' ', $packages);
        $result = $this->executeCommand("composer remove {$packageNames} --dev --no-interaction");

        if ($result === 0) {
            $io->write('<fg=magenta>  - Dev dependencies removed successfully.</>');
        } else {
            $io->writeError('<error>  - Failed to remove dev dependencies. Run "composer remove" manually.</error>');
        }

        $io->write('');
    }

    private function deleteOrphan(string $projectDir, string $orphanPath): void
    {
        $filePath = $projectDir . '/' . $orphanPath;

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $this->removeEmptyDirectories(dirname($filePath), $projectDir);
    }

    private function removeEmptyDirectories(string $directory, string $stopAt): void
    {
        while ($directory !== $stopAt && is_dir($directory)) {
            $files = array_diff(scandir($directory) ?: [], ['.', '..']);

            if ($files !== []) {
                break;
            }

            rmdir($directory);
            $directory = dirname($directory);
        }
    }

    /**
     * Install Laravel Boost's guidelines, skills and MCP entries, whatever
     * `boost.json` says.
     *
     * A full Laravel app is composed through its own `artisan`. A repository
     * with no `artisan` is composed through Orchestra Testbench, rooted at the
     * repository for this one command, and only when Testbench is installed.
     * The Boost MCP entries that run writes still name `artisan`; the
     * `routeMcpThroughTestbench` listener points them at Testbench afterwards.
     *
     * Boost composes from this package's `resources/boost` in vendor *and* from
     * the project's own `.ai`, so the run is unconditional: the package sources
     * alone can no longer tell us whether the output would change.
     *
     * Every composition passes through here, so this is where the agent files
     * are checked first. Boost overwrites hand-written content whenever a file
     * names its opening marker tag more than once, or leaves one unclosed, and
     * this package composes unattended at a moment the project's author did not
     * pick. `GuidelineGuard` carries the rule and the reasoning.
     */
    private function runBoost(IOInterface $io, string $projectDir, array $config): void
    {
        // On a first install Boost is still queued in `composer.install` below,
        // so there is no Boost command to call yet. Staying quiet beats a red
        // "failed": installing the dev dependencies runs this plugin again, and
        // that run composes.
        if (! is_dir($projectDir . '/vendor/laravel/boost')) {
            return;
        }

        $isApp = $this->isApp($projectDir);
        $installCommand = $isApp ? 'php artisan boost:install' : self::PACKAGE_BOOST_INSTALL;

        if (! $this->composesBoost($projectDir)) {
            $io->writeError(sprintf(
                '<comment>  This repository has no artisan and no %s, so Laravel Boost was not run. Require orchestra/testbench as a dev dependency to compose its guidelines and skills.</comment>',
                TestbenchMcp::TESTBENCH,
            ));

            return;
        }

        $guard = new GuidelineGuard;
        $hazards = $guard->hazards($projectDir);

        if ($hazards !== []) {
            $this->refuseBoost($io, $hazards);

            return;
        }

        // A fresh clone has no `boost.json` agents: the file is gitignored, and
        // a non-interactive install never records the agents it picks. Boost
        // then composes for whatever it detects on this machine, which may be
        // nothing at all.
        if (! (new BoostRegistrar)->hasAgents($projectDir)) {
            $io->writeError(sprintf(
                '<comment>  %s names no agents, so Laravel Boost composes for the agents it detects on this machine. Run "%s" once to choose them.</comment>',
                BoostRegistrar::FILE,
                $installCommand,
            ));
        }

        $description = $config['hooks']['description'] ?? 'Composing Laravel Boost...';
        $io->write("  <info>{$description}</info> ", false);

        // Every feature is passed explicitly, so a leftover boost.json setting
        // can never turn one off: Boost ignores boost.json once a flag is given.
        $startedAt = time();
        $result = $isApp
            ? $this->executeCommand(($config['hooks']['command'] ?? 'php artisan boost:install --no-interaction') . self::BOOST_FEATURES)
            : $this->composePackage($io, $projectDir, ($config['hooks']['package_command'] ?? self::PACKAGE_COMMAND) . self::BOOST_FEATURES);

        if ($result === null) {
            return;
        }

        if ($result !== 0) {
            $io->write('<error>failed</error>');
            $io->writeError(sprintf(
                '<error>  Laravel Boost exited with an error. Run "%s" to see why.%s</error>',
                $installCommand,
                $isApp ? ' Boost registers its commands only when APP_ENV is local or APP_DEBUG is true.' : '',
            ));

            return;
        }

        // Boost exits successfully when it found no agent to compose for, so a
        // zero exit alone would report "done" for a run that wrote nothing.
        if (! $guard->composedSince($projectDir, $startedAt)) {
            $io->write('<error>failed</error>');
            $io->writeError(sprintf(
                '<error>  Laravel Boost ran but composed no agent file: it found no agent to compose for. Run "%s" and choose your agents.</error>',
                $installCommand,
            ));

            return;
        }

        $io->write('<info>done</info>');
    }

    /**
     * Report the agent files composing would damage, and say what to do.
     *
     * The files are left exactly as they are. Repairing one means guessing
     * where its hand-written section ends, and a wrong guess destroys the
     * content this check exists to save.
     *
     * @param  array<string, string>  $hazards  relativePath => reason
     */
    private function refuseBoost(IOInterface $io, array $hazards): void
    {
        $io->writeError('<error>  Laravel Boost was not run: composing would overwrite hand-written content.</error>');

        foreach ($hazards as $path => $reason) {
            $io->writeError(sprintf('<comment>  %s %s</comment>', $path, $reason));
        }

        $io->writeError('<comment>  Edit the file yourself, then run the Composer command again. This package will not repair it: where your own text ends cannot be read from the file.</comment>');
    }

    private function composePackage(IOInterface $io, string $projectDir, string $command): ?int
    {
        try {
            foreach (self::TESTBENCH_DIRECTORIES as $directory) {
                CheckedFile::ensureDirectory($projectDir . '/' . $directory);
            }
        } catch (RuntimeException $exception) {
            $io->write('<error>failed</error>');
            $io->writeError(sprintf(
                '<error>  Laravel Boost was not run: Testbench cannot boot without its directories. %s</error>',
                rtrim($exception->getMessage(), '.') . '.',
            ));

            return null;
        }

        // The root is set on this one command, never on the Composer process:
        // a rooted `testbench boost:mcp` cannot run a single tool, because
        // Boost runs each one through the base path's `artisan`.
        return $this->executeCommand($command, ['APP_BASE_PATH' => $projectDir, 'APP_ENV' => 'local']);
    }

    private function isApp(string $projectDir): bool
    {
        return file_exists($projectDir . '/artisan');
    }

    /**
     * Whether Boost can run here at all: through `artisan` in an app, through
     * Testbench in a repository with none.
     */
    private function composesBoost(string $projectDir): bool
    {
        return $this->isApp($projectDir) || is_file($projectDir . '/' . TestbenchMcp::TESTBENCH);
    }

    private function executeCommand(string $command, array $environment = []): int
    {
        return (new SystemProcess)->run(command: $command, environment: $environment);
    }

    /**
     * Write one tracked file, and answer why it failed, or null when it did not.
     */
    private function writeTracked(FileSync $fileSync, string $projectDir, string $path, string $sourceFile): ?string
    {
        try {
            $fileSync->write($projectDir, $path, $sourceFile);
        } catch (RuntimeException $exception) {
            return $exception->getMessage();
        }

        return null;
    }

    private function getPackageDir(): ?string
    {
        $vendorDir = getcwd() . '/vendor/' . self::PACKAGE_NAME;

        if (is_dir($vendorDir)) {
            return realpath($vendorDir);
        }

        return null;
    }

    private function isRunningInOwnRepository(): bool
    {
        $composerFile = getcwd() . '/composer.json';

        if (! file_exists($composerFile)) {
            return false;
        }

        $data = json_decode((string) file_get_contents($composerFile), associative: true);

        return is_array($data) && ($data['name'] ?? null) === self::PACKAGE_NAME;
    }
}
