<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

/**
 * Append-only record of every MD5 checksum the package has ever shipped for
 * each tracked path.
 *
 * The manifest powers three things: deciding whether a downstream file is an
 * unmodified known version (safe to overwrite/delete) or a local edit (must be
 * protected), and detecting locally-modified files for upstream contribution.
 *
 * It is APPEND-ONLY: recording a checksum adds it to a path's known list and
 * never drops a path. Retaining keys for deleted source files is what lets
 * downstream orphan-cleanup keep firing after a file is removed upstream.
 *
 * `ManifestReader` loads one from disk. The reverse sync workflow requires
 * this file directly, with no Composer install, so it stays free of
 * dependencies.
 *
 * @phpstan-type ChecksumMap array<string, list<string>>
 */
final class Manifest
{
    /**
     * @param  ChecksumMap  $checksums
     */
    public function __construct(private array $checksums = [])
    {
    }

    /**
     * @return ChecksumMap
     */
    public function toArray(): array
    {
        $checksums = $this->checksums;

        ksort($checksums);

        return $checksums;
    }

    /**
     * Serialize to the established formatting contract: keys sorted,
     * pretty-printed, slashes unescaped, trailing newline.
     */
    public function toJson(): string
    {
        $json = json_encode($this->toArray(), flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return "{$json}\n";
    }

    public function dump(string $path): void
    {
        file_put_contents($path, $this->toJson());
    }

    /**
     * Append a checksum to a path's known list (deduped). Never removes keys.
     */
    public function record(string $path, string $checksum): void
    {
        $known = $this->knownChecksums($path);

        $this->checksums[$path] = array_values([...$known, ...array_diff([$checksum], $known)]);
    }

    /**
     * @return list<string>
     */
    public function knownChecksums(string $path): array
    {
        return $this->checksums[$path] ?? [];
    }

    public function isKnown(string $path, string $checksum): bool
    {
        return in_array($checksum, $this->knownChecksums($path), strict: true);
    }

    /**
     * @return list<string>
     */
    public function paths(): array
    {
        return array_keys($this->checksums);
    }
}
