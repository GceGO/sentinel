<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Branding;

use Gcego\Security\Tenancy\Tenant;
use Gcego\Sentinel\Contracts\AuthenticationBrandingResolver;
use Gcego\Sentinel\Contracts\TenantBrandingProvider;
use Gcego\Sentinel\Panels\AuthenticationPanel;
use Gcego\Sentinel\SentinelConfig;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;

/**
 * gcego-sentinel.branding (the name defaults to app.name), then the
 * panel's branding, then the tenant's: its name, and what the application's
 * TenantBrandingProvider (when bound) returns for it.
 */
final readonly class ConfiguredBrandingResolver implements AuthenticationBrandingResolver
{
    public function __construct(
        private SentinelConfig $config,
        private Repository $settings,
        private Container $container,
    ) {}

    public function resolve(?AuthenticationPanel $panel = null, ?Tenant $tenant = null): AuthenticationBranding
    {
        $branding = $this->config->branding;

        if ($branding->name === null) {
            $name = $this->settings->get('app.name');
            $branding = $branding->withName(is_string($name) && $name !== '' ? $name : 'GCEGO');
        }

        if ($panel !== null) {
            $branding = $branding->merge($panel->branding);
        }

        if ($panel !== null && $tenant !== null) {
            if ($tenant->name !== null && $tenant->name !== '') {
                $branding = $branding->withName($tenant->name);
            }

            if ($this->container->bound(TenantBrandingProvider::class)) {
                $tenantBranding = $this->container->make(TenantBrandingProvider::class)->branding($tenant, $panel);

                if ($tenantBranding !== null) {
                    $branding = $branding->merge($tenantBranding);
                }
            }
        }

        return $branding;
    }
}
