<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Support;

final class RootedTestbench
{
    public const SCRIPT = 'vendor/' . InstalledPackage::NAME . '/bin/rooted-testbench.php';

    public const MCP_SERVER = 'laravel-boost';

    public function __construct(private string $projectDir)
    {
    }

    public function replacement(string $argument): ?string
    {
        $directory = substr($argument, 0, -strlen(ProjectKind::ARTISAN));
        $isProjectArtisan = str_ends_with($argument, '/' . ProjectKind::ARTISAN)
            && realpath($directory) === realpath($this->projectDir);

        return match (true) {
            $argument === ProjectKind::ARTISAN => self::SCRIPT,
            $isProjectArtisan => $directory . self::SCRIPT,
            default => null,
        };
    }

    public function isScript(string $argument): bool
    {
        return str_ends_with($argument, self::SCRIPT);
    }
}
