<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Contracts;

use Gcego\Sentinel\Panels\AuthenticationPanel;

/**
 * The panels that have authentication pages. Configuration by default;
 * gcego/panels (or an application) can supply its own. Must not query:
 * it is read when routes are registered.
 */
interface AuthenticationPanels
{
    /**
     * @return array<string, AuthenticationPanel>
     */
    public function all(): array;

    public function find(string $id): ?AuthenticationPanel;

    /**
     * The panel a path lies in, with the (unverified) tenant key it names.
     *
     * @return array{panel: AuthenticationPanel, tenant: ?string}|null
     */
    public function forPath(string $path): ?array;
}
