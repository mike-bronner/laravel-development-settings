<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

use function Laravel\Prompts\multiselect;

use Laravel\Prompts\Prompt;
use MikeBronner\DevelopmentSettings\Support\BoostRegistrar;
use MikeBronner\DevelopmentSettings\Support\FileDiscovery;
use MikeBronner\DevelopmentSettings\Support\FileSync;
use MikeBronner\DevelopmentSettings\Support\GuidelineGuard;
use MikeBronner\DevelopmentSettings\Support\LegacySymlink;
use MikeBronner\DevelopmentSettings\Support\Manifest;
use MikeBronner\DevelopmentSettings\Support\SystemProcess;

final class ComposerPlugin implements EventSubscriberInterface, PluginInterface
{
    private const PACKAGE_NAME = 'mikebronner/development-settings';
    private const MANIFEST_FILE = 'manifest.json';
    private const BOX_WIDTH = 80;

    private static bool $dependenciesInjected = false;
    private IOInterface $io;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io): void {}

    public function uninstall(Composer $composer, IOInterface $io): void {}

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'publish',
            ScriptEvents::POST_UPDATE_CMD => 'publish',
        ];
    }

    public function publish(Event $event): void
    {
        $this->doPublish($event->getIO());
    }

    private function doPublish(IOInterface $io): void
    {
        $packageDir = $this->getPackageDir();

        if (! $packageDir) {
            // Running inside development-settings itself: nothing to publish.
            if (! $this->isRunningInOwnRepository()) {
                $io->writeError('<error>Could not locate developer-settings package directory</error>');
            }

            return;
        }

        $projectDir = getcwd();
        $config = require $packageDir . '/config/developer-settings.php';
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
            packageDir: $packageDir,
            linkPaths: $config['paths']['legacy_symlinks'] ?? [],
        );

        $fileSync = new FileSync($manifest);
        $scan = $fileSync->classify($projectDir, $filesToPublish);
        $safeOrphans = $fileSync->safeOrphans($projectDir, $filesToPublish);
        $protectedOrphans = $fileSync->protectedOrphans($projectDir, $filesToPublish);

        // Only where Boost can actually compose. A package has no artisan, so
        // registering it there would write a config file nothing ever reads.
        $registration = $this->composesBoost($projectDir)
            ? (new BoostRegistrar)->register($projectDir, self::PACKAGE_NAME)
            : null;

        $composerConfig = $config['composer'] ?? [];
        $dependencyResult = $this->prepareComposerDependencies(
            install: $composerConfig['install'] ?? [],
            remove: $composerConfig['remove'] ?? [],
        );

        $this->writeBoxHeader($io);

        foreach (array_keys($scan['new']) as $path) {
            $io->write($this->formatOutputLine(type: 'created', path: $path));
        }

        foreach (array_keys($scan['updatable']) as $path) {
            $io->write($this->formatOutputLine(type: 'updated', path: $path));
        }

        foreach (array_keys($scan['modified']) as $path) {
            $io->write($this->formatOutputLine(type: 'modified', path: $path));
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
            'new' => 0,
            'updated' => 0,
            'unchanged' => count($scan['unchanged']),
            'skipped' => 0,
            'removed' => 0,
        ];

        foreach ($scan['new'] as $path => $sourceFile) {
            $this->copyFile($sourceFile, $projectDir . '/' . $path);
            $stats['new']++;
        }

        foreach ($scan['updatable'] as $path => $sourceFile) {
            $this->copyFile($sourceFile, $projectDir . '/' . $path);
            $stats['updated']++;
        }

        foreach ($scan['modified'] as $path => $sourceFile) {
            if (in_array($path, $filesToOverwrite, true)) {
                $this->copyFile($sourceFile, $projectDir . '/' . $path);
                $stats['updated']++;

                continue;
            }

            $stats['skipped']++;
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

        $stats['removed'] += count($removedLinks);
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

        if ($registration === BoostRegistrar::UNREADABLE) {
            $io->writeError(sprintf(
                '<comment>  %s is not valid JSON, so this package could not be registered with Boost. Its guidelines and skills will not compose until you fix or delete it.</comment>',
                BoostRegistrar::FILE,
            ));
        }

        $this->runBoost($io, $projectDir, $config);
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
            'unlinked' => ['icon' => '-', 'style' => 'fg=magenta', 'suffix' => ' (stale symlink into vendor)'],
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
     * Compose the AI guidelines and skills (Laravel Boost) into agent files.
     *
     * Only full Laravel apps are composed, through their own `artisan`. A
     * package has no console entry point to run Boost with.
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
        // so there is no `boost:update` to call yet. Staying quiet beats a red
        // "failed": installing the dev dependencies runs this plugin again, and
        // that run composes.
        if (! $this->composesBoost($projectDir) || ! is_dir($projectDir . '/vendor/laravel/boost')) {
            return;
        }

        $hazards = (new GuidelineGuard)->hazards($projectDir);

        if ($hazards !== []) {
            $this->refuseBoost($io, $hazards);

            return;
        }

        $description = $config['hooks']['description'] ?? 'Updating Laravel Boost...';
        $io->write("  <info>{$description}</info> ", false);

        $result = $this->executeCommand($config['hooks']['command'] ?? 'php artisan boost:update');

        $io->write($result === 0 ? '<info>done</info>' : '<error>failed</error>');
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

    /**
     * Whether Boost can compose here. Full Laravel apps have `artisan`;
     * packages have no console entry point of their own.
     */
    private function composesBoost(string $projectDir): bool
    {
        return file_exists($projectDir . '/artisan');
    }

    private function executeCommand(string $command): int
    {
        return (new SystemProcess)->run($command);
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

    private function copyFile(string $source, string $destination): void
    {
        $directory = dirname($destination);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        copy($source, $destination);
    }
}
