<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Context;

use Gcego\Security\Contracts\TenantResolver;
use Gcego\Security\Tenancy\Tenant;
use Gcego\Sentinel\AuthenticationPage;
use Gcego\Sentinel\Contracts\AuthenticationBrandingResolver;
use Gcego\Sentinel\Contracts\AuthenticationContextResolver;
use Gcego\Sentinel\Contracts\AuthenticationFeatureResolver;
use Gcego\Sentinel\Contracts\AuthenticationPanels;
use Gcego\Sentinel\Layouts\LayoutManager;
use Gcego\Sentinel\Panels\AuthenticationPanel;
use Gcego\Sentinel\SentinelConfig;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\UrlGenerator;

/**
 * The panel of a page comes from Sentinel's own panel routes (trusted
 * route metadata) or, on the shared pages (two-factor challenge,
 * forgot password...), from the session the panel's login page started;
 * either way it is looked up in configuration. A tenant key is only used
 * to find the tenant for branding: nothing is granted until gcego/security
 * verifies the membership after signing in.
 */
final readonly class SentinelContextResolver implements AuthenticationContextResolver
{
    public const string RoutePanel = 'sentinel_panel';

    public function __construct(
        private SentinelConfig $config,
        private AuthenticationPanels $panels,
        private AuthenticationFeatureResolver $features,
        private AuthenticationBrandingResolver $branding,
        private LayoutManager $layouts,
        private TenantResolver $tenants,
        private UrlGenerator $urls,
    ) {}

    public function resolve(Request $request, AuthenticationPage $page): AuthenticationContext
    {
        [$panel, $tenantKey] = $this->panel($request);
        $tenant = $panel !== null && $tenantKey !== null ? $this->tenant($tenantKey) : null;

        return new AuthenticationContext(
            $page,
            $this->layouts->get($panel->layout ?? null),
            $this->branding->resolve($panel, $tenant),
            $panel->theme ?? $this->config->theme,
            $this->config->showBranding,
            $this->features->available($panel),
            $this->urls,
            $panel,
            $tenant,
            $tenant === null ? null : $tenantKey,
        );
    }

    /**
     * @return array{0: ?AuthenticationPanel, 1: ?string}
     */
    public function panel(Request $request): array
    {
        $route = $request->route();
        $id = $route instanceof Route ? $route->getAction(self::RoutePanel) : null;

        if (is_string($id)) {
            $panel = $this->panels->find($id);
            $tenant = $route instanceof Route ? $route->parameter('tenant') : null;

            return [$panel, $panel?->isTenant() === true && is_string($tenant) && self::isTenantKey($tenant) ? $tenant : null];
        }

        if (! $request->hasSession()) {
            return [null, null];
        }

        $id = $request->session()->get(SessionKeys::Panel);
        $panel = is_string($id) ? $this->panels->find($id) : null;
        $tenant = $request->session()->get(SessionKeys::Tenant);

        return [$panel, $panel?->isTenant() === true && is_string($tenant) && self::isTenantKey($tenant) ? $tenant : null];
    }

    /**
     * The tenant a key names, for presentation only (null when unknown).
     */
    public function tenant(string $key): ?Tenant
    {
        return self::isTenantKey($key) ? $this->tenants->find($key) : null;
    }

    public static function isTenantKey(string $key): bool
    {
        return preg_match('/^'.AuthenticationPanel::TenantKeyPattern.'$/', $key) === 1;
    }
}
