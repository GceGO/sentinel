<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Contracts;

use Gcego\Security\Tenancy\Tenant;
use Gcego\Sentinel\Branding\AuthenticationBranding;
use Gcego\Sentinel\Panels\AuthenticationPanel;

/**
 * Application-supplied branding of a tenant (an institution's name, logo
 * and colors) for its tenant panel's login page. The tenant was found by
 * gcego/security's TenantResolver from an untrusted URL key, so return
 * only what may be shown publicly; the values are validated like
 * configuration (AuthenticationBranding).
 */
interface TenantBrandingProvider
{
    public function branding(Tenant $tenant, AuthenticationPanel $panel): ?AuthenticationBranding;
}
