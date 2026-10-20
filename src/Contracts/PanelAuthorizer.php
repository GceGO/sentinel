<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Contracts;

use Gcego\Sentinel\Panels\AuthenticationPanel;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Whether a signed-in user may enter a panel (for a tenant panel: the given,
 * untrusted tenant key, whose membership must be verified). Signing in
 * never implies this.
 */
interface PanelAuthorizer
{
    public function allows(Authenticatable $user, AuthenticationPanel $panel, ?string $tenant = null): bool;
}
