<?php

declare(strict_types=1);

use MikeBronner\DevelopmentSettings\Support\ManagedSection;

const GITIGNORE_V1 = "/vendor\n";

const GITIGNORE_V2 = "/vendor\n/node_modules\n";

/**
 * A managed file: the lines above the sync marker, the marker, and whatever
 * follows it on its line and below.
 */
function marked(string $above, string $after = "\n"): string
{
    return $above . ManagedSection::MARKER . $after;
}
