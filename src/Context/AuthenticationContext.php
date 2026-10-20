<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Context;

use Gcego\Security\Tenancy\Tenant;
use Gcego\Sentinel\AuthenticationPage;
use Gcego\Sentinel\Branding\AuthenticationBranding;
use Gcego\Sentinel\Contracts\AuthenticationLayout;
use Gcego\Sentinel\Features\Feature;
use Gcego\Sentinel\Panels\AuthenticationPanel;
use Gcego\Sentinel\Routing\SentinelRoutes;
use Gcego\Sentinel\Support\Routes;
use Illuminate\Routing\UrlGenerator;

/**
 * Everything one authentication page render needs, resolved per request:
 * the page, the panel and tenant (for presentation: the tenant was found,
 * not verified), the layout, branding and theme, and the features that are
 * really available. Views read links from here, so a link to a disabled
 * feature or a missing route is never rendered.
 */
final readonly class AuthenticationContext
{
    /**
     * @param  list<Feature>  $features
     */
    public function __construct(
        public AuthenticationPage $page,
        public AuthenticationLayout $layout,
        public AuthenticationBranding $branding,
        public string $theme,
        public bool $showBranding,
        public array $features,
        private UrlGenerator $urls,
        public ?AuthenticationPanel $panel = null,
        public ?Tenant $tenant = null,
        public ?string $tenantKey = null,
    ) {}

    public function has(Feature $feature): bool
    {
        return in_array($feature, $this->features, true);
    }

    public function title(): string
    {
        return $this->page->title();
    }

    /**
     * The panel's title ("Administration"), or null outside a panel.
     */
    public function panelTitle(): ?string
    {
        if ($this->panel === null) {
            return null;
        }

        $title = $this->panel->title === null ? null : __($this->panel->title);

        return is_string($title) ? $title : $this->panel->title;
    }

    /**
     * A route's URL, or null when the application does not register it.
     *
     * @param  array<string, string|int>  $parameters
     */
    public function url(string $route, array $parameters = []): ?string
    {
        return Routes::has($route) ? $this->urls->route($route, $parameters) : null;
    }

    public function loginUrl(): ?string
    {
        if ($this->panel !== null && ($url = $this->url(SentinelRoutes::panelLogin($this->panel), $this->panelParameters())) !== null) {
            return $url;
        }

        return $this->url('login');
    }

    public function registerUrl(): ?string
    {
        if (! $this->has(Feature::Registration)) {
            return null;
        }

        if ($this->panel !== null) {
            return $this->url(SentinelRoutes::panelRegister($this->panel), $this->panelParameters());
        }

        return $this->url('register');
    }

    public function forgotPasswordUrl(): ?string
    {
        return $this->has(Feature::PasswordReset) ? $this->url('password.request') : null;
    }

    /**
     * Fields that tell the logout endpoint which panel's sign-out page to show
     * (validated against configuration there).
     *
     * @return array<string, string>
     */
    public function logoutFields(): array
    {
        if ($this->panel === null) {
            return [];
        }

        return array_filter(['panel' => $this->panel->id, 'tenant' => $this->panel->isTenant() ? $this->tenantKey : null], static fn (?string $value): bool => $value !== null);
    }

    /**
     * @return array<string, string>
     */
    private function panelParameters(): array
    {
        return $this->panel?->isTenant() === true ? ['tenant' => (string) $this->tenantKey] : [];
    }
}
