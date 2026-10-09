<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

final class ManifestReader
{
    public function read(string $path): Manifest
    {
        return match (file_exists($path)) {
            true => new Manifest($this->decode((string) file_get_contents($path))),
            false => new Manifest,
        };
    }

    private function decode(string $json): array
    {
        $decoded = json_decode(json: $json, associative: true);

        return match (is_array($decoded)) {
            true => $decoded,
            false => [],
        };
    }
}
