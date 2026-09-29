<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

/**
 * Names this package, and any other package whose guidelines should compose,
 * under the `packages` key of the consuming project's `boost.json`.
 *
 * Laravel Boost discovers a package's `resources/boost/guidelines` and
 * `resources/boost/skills` directories on its own, but then composes nothing
 * from them unless `boost.json` lists the package:
 * `InstallCommand::selectThirdPartyPackages()` returns only what the file
 * already names when it runs non-interactively, and that selection becomes the
 * filter for both composers. Boost cannot add the entry itself during a
 * Composer run — `UpdateCommand::discoverNewContent()` returns early whenever
 * `COMPOSER_DEV_MODE` is set, which Composer sets for the whole install. So the
 * plugin writes it, or composition is silently empty.
 *
 * The write matches Boost's own formatting contract (keys sorted,
 * pretty-printed, slashes unescaped, trailing newline) so Boost rewriting the
 * file afterwards produces no churn.
 */
final class BoostRegistrar
{
    public const REGISTERED = 'registered';

    public const UNCHANGED = 'unchanged';

    public const UNREADABLE = 'unreadable';

    public const FILE = 'boost.json';

    /**
     * Ensure `$package` is listed, and that none of the names in `$replaces`
     * is. Creates `boost.json` when absent — the entry is inert until Boost is
     * installed, and then seeds the default selection.
     *
     * `$replaces` holds names this package itself shipped under earlier. An
     * upgraded project still lists them, and Boost would keep filtering on a
     * package that is no longer installed. Only those exact entries are
     * removed: every other package in the list belongs to the project.
     *
     * `$alongside` holds other packages to list after `$package`, each only
     * when it is missing.
     *
     * A file that is not a JSON object, or whose `packages` is not a list, is
     * left alone and answered as unreadable.
     *
     * @param  list<string>  $replaces
     * @param  list<string>  $alongside
     * @return self::REGISTERED|self::UNCHANGED|self::UNREADABLE
     */
    public function register(
        string $projectDir,
        string $package,
        array $replaces = [],
        array $alongside = [],
    ): string {
        $path = "{$projectDir}/" . self::FILE;
        $config = $this->read($path);
        $packages = data_get($config, 'packages') ?? [];

        return match (true) {
            $config === null,
            ! is_array($packages) => self::UNREADABLE,
            default => $this->registration(
                $path,
                $config,
                $this->listed($packages, [$package, ...$alongside], $replaces),
            ),
        };
    }

    /**
     * Whether `boost.json` names at least one agent to compose for. An absent
     * or unreadable file names none.
     */
    public function hasAgents(string $projectDir): bool
    {
        $agents = data_get($this->read("{$projectDir}/" . self::FILE), 'agents', []);

        return is_array($agents) && $agents !== [];
    }

    /**
     * The package list with the replaced names dropped and every wanted
     * package present, each missing one appended in order.
     *
     * @param  array<array-key, mixed>  $packages
     * @param  list<string>  $wanted
     * @param  list<string>  $replaces
     * @return array{before: list<mixed>, after: list<mixed>}
     */
    private function listed(array $packages, array $wanted, array $replaces): array
    {
        $kept = collect($packages)
            ->reject(static fn (mixed $entry): bool => in_array($entry, $replaces, strict: true))
            ->values();
        $missing = collect($wanted)
            ->reject(static fn (string $name): bool => $kept->containsStrict($name));

        return ['before' => array_values($packages), 'after' => $kept->concat($missing)->all()];
    }

    /**
     * Write the new list when it differs from the one on disk.
     *
     * @param  array<string, mixed>  $config
     * @param  array{before: list<mixed>, after: list<mixed>}  $packages
     * @return self::REGISTERED|self::UNCHANGED
     */
    private function registration(string $path, array $config, array $packages): string
    {
        ['before' => $before, 'after' => $after] = $packages;

        return match ($after === $before) {
            true => self::UNCHANGED,
            false => $this->registerList($path, [...$config, 'packages' => $after]),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     * @return self::REGISTERED
     */
    private function registerList(string $path, array $config): string
    {
        ksort($config);

        $json = json_encode($config, flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($path, "{$json}\n");

        return self::REGISTERED;
    }

    /**
     * An absent file is an empty config to be created. Anything present that is
     * not a JSON object is left alone — a hand-edited or corrupt `boost.json`
     * belongs to the developer, and overwriting it would destroy their agent,
     * guideline and MCP settings.
     *
     * @return array<string, mixed>|null
     */
    private function read(string $path): ?array
    {
        return match (file_exists($path)) {
            true => $this->decode((string) file_get_contents($path)),
            false => [],
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(string $json): ?array
    {
        $decoded = json_decode(json: $json, associative: true);

        return match (is_array($decoded)) {
            true => $decoded,
            false => null,
        };
    }
}
