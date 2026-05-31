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
 * @phpstan-type ChecksumMap array<string, list<string>>
 */
final class Manifest
{
    /**
     * @param  ChecksumMap  $checksums
     */
    public function __construct(private array $checksums = []) {}

    public static function load(string $path): self
    {
        if (! file_exists($path)) {
            return new self;
        }

        $decoded = json_decode(json: (string) file_get_contents($path), associative: true);

        return new self(is_array($decoded) ? $decoded : []);
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
     * Write the manifest to disk, matching the established formatting contract:
     * keys sorted, pretty-printed, slashes unescaped, trailing newline.
     */
    public function dump(string $path): void
    {
        file_put_contents(
            $path,
            json_encode($this->toArray(), flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        );
    }

    /**
     * Append a checksum to a path's known list (deduped). Never removes keys.
     */
    public function record(string $path, string $checksum): void
    {
        $known = $this->checksums[$path] ?? [];

        if (! in_array($checksum, $known, strict: true)) {
            $known[] = $checksum;
        }

        $this->checksums[$path] = array_values($known);
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
