<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

final class Manifest
{
    public function __construct(private array $checksums = [])
    {
    }

    public function toArray(): array
    {
        $checksums = $this->checksums;

        ksort($checksums);

        return $checksums;
    }

    public function toJson(): string
    {
        $json = json_encode($this->toArray(), flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return "{$json}\n";
    }

    public function dump(string $path): void
    {
        file_put_contents($path, $this->toJson());
    }

    public function record(string $path, string $checksum): void
    {
        $known = $this->knownChecksums($path);

        $this->checksums[$path] = array_values([...$known, ...array_diff([$checksum], $known)]);
    }

    public function knownChecksums(string $path): array
    {
        return $this->checksums[$path] ?? [];
    }

    public function isKnown(string $path, string $checksum): bool
    {
        return in_array($checksum, $this->knownChecksums($path), strict: true);
    }

    public function paths(): array
    {
        return array_keys($this->checksums);
    }
}
