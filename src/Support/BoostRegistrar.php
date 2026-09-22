<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

/**
 * Names this package under the `packages` key of the consuming project's
 * `boost.json`.
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
     * Ensure `$package` is listed. Creates `boost.json` when absent — the entry
     * is inert until Boost is installed, and then seeds the default selection.
     *
     * @return self::REGISTERED|self::UNCHANGED|self::UNREADABLE
     */
    public function register(string $projectDir, string $package): string
    {
        $path = $projectDir . '/' . self::FILE;
        $config = $this->read($path);

        if ($config === null) {
            return self::UNREADABLE;
        }

        $packages = $config['packages'] ?? [];

        if (! is_array($packages)) {
            return self::UNREADABLE;
        }

        if (in_array($package, $packages, strict: true)) {
            return self::UNCHANGED;
        }

        $packages[] = $package;
        $config['packages'] = array_values($packages);
        ksort($config);

        file_put_contents(
            $path,
            json_encode($config, flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        );

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
        if (! file_exists($path)) {
            return [];
        }

        $decoded = json_decode(json: (string) file_get_contents($path), associative: true);

        return is_array($decoded) ? $decoded : null;
    }
}
