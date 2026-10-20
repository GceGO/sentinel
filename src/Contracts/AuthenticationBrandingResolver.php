<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Contracts;

use Gcego\Security\Tenancy\Tenant;
use Gcego\Sentinel\Branding\AuthenticationBranding;
use Gcego\Sentinel\Panels\AuthenticationPanel;

/**
 * The branding of an authentication page: configuration, then the panel's,
 * then the tenant's (TenantBrandingProvider) on top.
 */
interface AuthenticationBrandingResolver
{
    public function resolve(?AuthenticationPanel $panel = null, ?Tenant $tenant = null): AuthenticationBranding;
}
