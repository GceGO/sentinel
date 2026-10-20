<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Panels;

use Gcego\Sentinel\Contracts\AuthenticationPanels;
use Gcego\Sentinel\SentinelConfig;

/**
 * The panels of gcego-sentinel.panels. The longest matching path wins, so
 * "admin/schools" is found before "admin".
 */
final readonly class ConfiguredPanels implements AuthenticationPanels
{
    public function __construct(private SentinelConfig $config) {}

    public function all(): array
    {
        return $this->config->panels;
    }

    public function find(string $id): ?AuthenticationPanel
    {
        return $this->config->panels[$id] ?? null;
    }

    public function forPath(string $path): ?array
    {
        $panels = $this->config->panels;
        uasort($panels, static fn (AuthenticationPanel $a, AuthenticationPanel $b): int => strlen($b->path) <=> strlen($a->path));

        foreach ($panels as $panel) {
            $match = $panel->match('/'.ltrim($path, '/'));

            if ($match !== null) {
                return ['panel' => $panel, 'tenant' => $match['tenant']];
            }
        }

        return null;
    }
}
