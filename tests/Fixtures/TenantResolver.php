<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Tests\Fixtures;

use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Contracts\TenantResolver as Contract;
use Gcego\Security\Tenancy\Tenant;
use Gcego\Security\Tenancy\TenantMembership;
use Gcego\Sentinel\Tests\Fixtures\Models\Membership;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Two institutions; memberships (with tenant permissions) from the memberships table.
 */
final class TenantResolver implements Contract
{
    public const array Institutions = ['1' => 'Lycée Bilingue de Buea', '2' => 'GBHS Limbe'];

    public static int $lookups = 0;

    public function find(string $key): ?Tenant
    {
        self::$lookups++;

        return isset(self::Institutions[$key]) ? new Tenant('institution', (int) $key, null, self::Institutions[$key]) : null;
    }

    public function membership(Authenticatable $user, Tenant $tenant): ?TenantMembership
    {
        $membership = Membership::query()->where('user_id', $user->getAuthIdentifier())->where('institution_id', $tenant->key)->first();

        if ($membership === null) {
            return null;
        }

        $permissions = $membership->getAttribute('permissions');

        return new TenantMembership($tenant, AuthorizationContext::keyOf($user), ['member'], is_array($permissions) ? array_values(array_filter($permissions, is_string(...))) : []);
    }
}
