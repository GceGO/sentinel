<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Tests\Fixtures;

use Gcego\Security\Tenancy\Tenant;
use Gcego\Sentinel\Branding\AuthenticationBranding;
use Gcego\Sentinel\Contracts\TenantBrandingProvider;
use Gcego\Sentinel\Panels\AuthenticationPanel;

final class TenantBranding implements TenantBrandingProvider
{
    public function branding(Tenant $tenant, AuthenticationPanel $panel): ?AuthenticationBranding
    {
        return (string) $tenant->key === '1' ? new AuthenticationBranding(logo: '/images/lbb.svg', accent: '#047857') : null;
    }
}
