<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Plugin;

final class Summary
{
    private const BOX_WIDTH = 80;

    private const RULE_WIDTH = self::BOX_WIDTH - 2;

    private const TITLE_PADDING = self::BOX_WIDTH - 24;

    private const CONTENT_WIDTH = self::BOX_WIDTH - 6;

    private const ICON_WIDTH = 1;

    private const ICON_GAP = 2;

    private const MAX_PATH_LENGTH = self::CONTENT_WIDTH - self::ICON_WIDTH - self::ICON_GAP - 1;

    private const ELLIPSIS = '...';

    private const MIN_PADDING = 1;

    private const ITEMS = [
        Tally::NEW => ['green', 'bright-green'],
        Tally::UPDATED => ['yellow', 'bright-yellow'],
        Tally::UNCHANGED => ['gray', 'white'],
        Tally::SKIPPED => ['red', 'bright-red'],
        Tally::REMOVED => ['magenta', 'bright-magenta'],
    ];

    public function header(): array
    {
        $rule = str_repeat('─', self::RULE_WIDTH);
        $padding = str_repeat(' ', self::TITLE_PADDING);

        return [
            '',
            "<fg=gray>┌{$rule}┐</>",
            "<fg=gray>│</>  <fg=cyan>Developer Settings</>{$padding}  <fg=gray>│</>",
            "<fg=gray>├{$rule}┤</>",
        ];
    }

    public function line(string $type, string $path): string
    {
        $style = $this->style($type);
        $icon = $this->icon($type);
        $displayPath = $this->fitted($this->described($type, $path));
        $visibleLength = self::ICON_WIDTH + self::ICON_GAP + strlen($displayPath);
        $padding = str_repeat(' ', max(self::MIN_PADDING, self::CONTENT_WIDTH - $visibleLength));

        $prefix = "<{$style}>{$icon}</{$style}>";

        return "<fg=gray>│</>  {$prefix}  {$displayPath}{$padding}  <fg=gray>│</>";
    }

    public function footer(Tally $tally): array
    {
        $rule = str_repeat('─', self::RULE_WIDTH);
        $items = [];

        foreach (self::ITEMS as $kind => [$background, $foreground]) {
            $items[] = $this->item($tally->count($kind), $kind, $background, $foreground);
        }

        $summary = implode(' · ', $items);
        $plain = (string) preg_replace('/<[^>]+>/', '', $summary);
        $padding = str_repeat(' ', self::CONTENT_WIDTH - mb_strlen($plain));

        return [
            "<fg=gray>├{$rule}┤</>",
            "<fg=gray>│</>  {$summary}{$padding}  <fg=gray>│</>",
            "<fg=gray>└{$rule}┘</>",
            '',
        ];
    }

    private function item(int $count, string $label, string $background, string $foreground): string
    {
        return match ($count) {
            0 => "<fg=gray>{$count} {$label}</>",
            default => "<fg={$foreground};bg={$background}> {$count} {$label} </>",
        };
    }

    private function icon(string $type): string
    {
        return match ($type) {
            'created', 'registered' => '+',
            'updated' => '↻',
            'failed' => '✗',
            'removed', 'unlinked', 'stale_fingerprint' => '-',
            default => '⚠',
        };
    }

    private function style(string $type): string
    {
        return match ($type) {
            'created', 'registered' => 'info',
            'updated' => 'comment',
            'refused', 'failed' => 'fg=red',
            'removed', 'unlinked', 'stale_fingerprint' => 'fg=magenta',
            default => 'fg=yellow',
        };
    }

    private function described(string $type, string $path): string
    {
        return match ($type) {
            'modified' => "{$path} (locally modified)",
            'unmarked' => "{$path} (locally modified, no sync marker)",
            'refused' => "{$path} (sync marker appears twice, not touched)",
            'orphan_protected' => "{$path} (removed upstream, kept — locally modified)",
            'failed' => "{$path} (write failed)",
            'unlinked' => "{$path} (stale symlink into vendor)",
            'stale_fingerprint' => "{$path} (stale Boost fingerprint)",
            'registered' => "{$path} (registered with Boost)",
            default => $path,
        };
    }

    private function fitted(string $displayPath): string
    {
        $cut = self::MAX_PATH_LENGTH - strlen(self::ELLIPSIS);

        return match (strlen($displayPath) > self::MAX_PATH_LENGTH) {
            true => substr($displayPath, 0, $cut) . self::ELLIPSIS,
            false => $displayPath,
        };
    }
}
