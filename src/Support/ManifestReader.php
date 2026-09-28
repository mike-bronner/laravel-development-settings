<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

/**
 * Loads a `Manifest` from a JSON file on disk.
 *
 * A missing file, one that does not parse, and one that is not a JSON object
 * all load as an empty manifest. The reverse sync refuses an empty one, so
 * none of the three can read as "every tracked file is modified" there.
 *
 * The reverse sync workflow requires this file directly, with no Composer
 * install, so it stays free of dependencies.
 */
final class ManifestReader
{
    public function read(string $path): Manifest
    {
        return match (file_exists($path)) {
            true => new Manifest($this->decode((string) file_get_contents($path))),
            false => new Manifest(),
        };
    }

    /**
     * @return array<string, list<string>>
     */
    private function decode(string $json): array
    {
        $decoded = json_decode(json: $json, associative: true);

        return match (is_array($decoded)) {
            true => $decoded,
            false => [],
        };
    }
}
