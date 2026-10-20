<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Panels;

use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Contracts\Requirement;
use Gcego\Security\Exceptions\TenantAccessException;
use Gcego\Security\Permissions\Permission;
use Gcego\Security\Requirements\Authenticated;
use Gcego\Security\Requirements\TenantMember;
use Gcego\Security\SecurityManager;
use Gcego\Sentinel\Contracts\PanelAuthorizer;
use Gcego\Sentinel\SentinelConfig;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Panel access through gcego/security, for the given user (not whoever the
 * guard holds), evaluated fresh:
 *
 *  - a panel without a permission only requires a signed-in user;
 *  - a panel permission is a global permission (PermissionProvider);
 *  - a tenant panel first verifies the user's membership in the tenant
 *    (unknown tenants and non-members are denied alike), then checks the
 *    permission inside that tenant (Permission::inTenant), so global roles
 *    and Gate::before rules never open another institution's panel.
 */
final readonly class SecurityPanelAuthorizer implements PanelAuthorizer
{
    public function __construct(
        private SecurityManager $security,
        private SentinelConfig $config,
    ) {}

    public function allows(Authenticatable $user, AuthenticationPanel $panel, ?string $tenant = null): bool
    {
        if ($panel->isTenant() && ($tenant === null || $tenant === '')) {
            return false;
        }

        $guard = $this->guard();
        /** @var list<Requirement> $requirements */
        $requirements = [new Authenticated($guard)];

        if ($panel->isTenant()) {
            $requirements[] = $panel->permission === null ? new TenantMember : Permission::inTenant($panel->permission);
        } elseif ($panel->permission !== null) {
            $requirements[] = new Permission($panel->permission);
        }

        try {
            return $this->security->actingAs(
                $user,
                $panel->isTenant() ? $tenant : null,
                fn (AuthorizationContext $context): bool => $this->security->check(...$requirements)->allowed,
                $guard,
            );
        } catch (TenantAccessException) {
            return false;
        }
    }

    private function guard(): string
    {
        return $this->config->guard;
    }
}
