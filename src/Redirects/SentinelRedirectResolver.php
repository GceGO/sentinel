<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Redirects;

use Gcego\Sentinel\Context\SessionKeys;
use Gcego\Sentinel\Contracts\AuthenticationFeatureResolver;
use Gcego\Sentinel\Contracts\AuthenticationPanels;
use Gcego\Sentinel\Contracts\AuthenticationRedirectResolver;
use Gcego\Sentinel\Contracts\PanelAuthorizer;
use Gcego\Sentinel\Features\Feature;
use Gcego\Sentinel\Panels\AuthenticationPanel;
use Gcego\Sentinel\Routing\SentinelRoutes;
use Gcego\Sentinel\Security\TwoFactorPolicy;
use Gcego\Sentinel\SentinelConfig;
use Gcego\Sentinel\Support\Routes;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;

/**
 * Panel-aware destinations:
 *
 *  1. the intended URL, when it is a safe internal URL;
 *  2. if it lies inside a panel (or the user signed in on a panel's login
 *     page), the user must be allowed into that panel and, for a tenant
 *     panel, that tenant (gcego/security); otherwise the login is rejected;
 *  3. users who must use two-factor authentication but have not enrolled
 *     are sent to the account security page first;
 *  4. without a panel: the intended URL or the configured home.
 *
 * Redirects therefore never cross into a panel or tenant the user may not enter.
 */
final readonly class SentinelRedirectResolver implements AuthenticationRedirectResolver
{
    public function __construct(
        private SentinelConfig $config,
        private SafeRedirectValidator $validator,
        private AuthenticationPanels $panels,
        private PanelAuthorizer $authorizer,
        private TwoFactorPolicy $twoFactor,
        private AuthenticationFeatureResolver $features,
        private UrlGenerator $urls,
    ) {}

    public function afterLogin(Request $request, Authenticatable $user): Destination
    {
        $intended = $this->validator->internal($request->session()->pull('url.intended'), $request);
        $match = $intended === null ? null : $this->panels->forPath(SafeRedirectValidator::path($intended));
        [$panel, $tenant] = $match !== null ? [$match['panel'], $match['tenant']] : $this->sessionPanel($request);

        if ($panel === null) {
            return $this->withTwoFactor($user, Destination::to($intended ?? $this->home()), null);
        }

        if (! $this->authorizer->allows($user, $panel, $tenant)) {
            return Destination::rejected('panel_denied', $panel, $tenant);
        }

        return $this->withTwoFactor($user, Destination::to($match !== null && $intended !== null ? $intended : $panel->home($tenant), $panel, $tenant), $panel);
    }

    public function afterRegistration(Request $request, Authenticatable $user): Destination
    {
        [$panel, $tenant] = $this->sessionPanel($request);

        // A new account is never assumed to be allowed into the panel it registered from.
        if ($panel !== null && $this->authorizer->allows($user, $panel, $tenant)) {
            return Destination::to($panel->home($tenant), $panel, $tenant);
        }

        return Destination::to($this->home());
    }

    public function afterVerification(Request $request, Authenticatable $user): Destination
    {
        $intended = $this->validator->internal($request->session()->pull('url.intended'), $request);
        $match = $intended === null ? null : $this->panels->forPath(SafeRedirectValidator::path($intended));

        if ($intended !== null && ($match === null || $this->authorizer->allows($user, $match['panel'], $match['tenant']))) {
            return Destination::to(self::withQuery($intended, 'verified=1'));
        }

        [$panel, $tenant] = $this->sessionPanel($request);

        if ($panel !== null && $this->authorizer->allows($user, $panel, $tenant)) {
            return Destination::to(self::withQuery($panel->home($tenant), 'verified=1'), $panel, $tenant);
        }

        return Destination::to(self::withQuery($this->home(), 'verified=1'));
    }

    public function afterPasswordReset(Request $request): string
    {
        [$panel, $tenant] = $this->sessionPanel($request);

        return $panel !== null ? $this->panelLogin($panel, $tenant) : $this->route('login', '/login');
    }

    public function afterLogout(Request $request): string
    {
        $panel = is_string($id = $request->input('panel')) ? $this->panels->find($id) : null;

        if ($panel !== null) {
            $tenant = is_string($key = $request->input('tenant')) && preg_match('/^'.AuthenticationPanel::TenantKeyPattern.'$/', $key) === 1 ? $key : null;

            if ($panel->logoutRedirect !== null) {
                return $panel->logoutRedirect;
            }

            if (! $panel->isTenant() || $tenant !== null) {
                return $this->panelLogin($panel, $tenant);
            }
        }

        if ($this->config->logoutRedirect !== null) {
            return $this->config->logoutRedirect;
        }

        return $this->route(SentinelRoutes::LoggedOut, '/');
    }

    public function login(Request $request): string
    {
        $match = $this->panels->forPath('/'.ltrim($request->path(), '/'));

        if ($match !== null && (! $match['panel']->isTenant() || $match['tenant'] !== null)) {
            return $this->panelLogin($match['panel'], $match['tenant']);
        }

        return $this->route('login', '/login');
    }

    public function panelLogin(AuthenticationPanel $panel, ?string $tenant = null): string
    {
        $name = SentinelRoutes::panelLogin($panel);

        if (Routes::has($name)) {
            return $this->urls->route($name, $panel->isTenant() ? ['tenant' => (string) $tenant] : [], false);
        }

        return $this->route('login', '/login');
    }

    public function home(): string
    {
        return $this->config->home;
    }

    /**
     * The panel a sign-in in progress is for (the intended URL's, else the
     * login page's), without consuming the intended URL.
     */
    public function panelFor(Request $request): ?AuthenticationPanel
    {
        $intended = $this->validator->internal($request->session()->get('url.intended'), $request);
        $match = $intended === null ? null : $this->panels->forPath(SafeRedirectValidator::path($intended));

        return $match !== null ? $match['panel'] : $this->sessionPanel($request)[0];
    }

    /**
     * The panel (and its untrusted tenant key) whose login page started this
     * sign-in, as remembered in the session; unknown panels are ignored.
     *
     * @return array{0: ?AuthenticationPanel, 1: ?string}
     */
    private function sessionPanel(Request $request): array
    {
        $id = $request->session()->get(SessionKeys::Panel);
        $panel = is_string($id) ? $this->panels->find($id) : null;

        if ($panel === null) {
            return [null, null];
        }

        $tenant = $request->session()->get(SessionKeys::Tenant);
        $tenant = is_string($tenant) && preg_match('/^'.AuthenticationPanel::TenantKeyPattern.'$/', $tenant) === 1 ? $tenant : null;

        return $panel->isTenant() && $tenant === null ? [null, null] : [$panel, $panel->isTenant() ? $tenant : null];
    }

    private function withTwoFactor(Authenticatable $user, Destination $destination, ?AuthenticationPanel $panel): Destination
    {
        if ($destination->url === null || ! $this->twoFactor->missing($user, $panel) || ! $this->features->enabled(Feature::AccountSecurity)) {
            return $destination;
        }

        return Destination::twoFactorSetup($this->route(SentinelRoutes::AccountSecurity, $destination->url), $destination->url, $destination->panel, $destination->tenant);
    }

    private function route(string $name, string $fallback): string
    {
        return Routes::has($name) ? $this->urls->route($name, [], false) : $fallback;
    }

    private static function withQuery(string $url, string $query): string
    {
        $fragment = '';

        if (str_contains($url, '#')) {
            [$url, $fragment] = explode('#', $url, 2);
            $fragment = '#'.$fragment;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').$query.$fragment;
    }
}
