<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Routing;

use Gcego\Sentinel\AuthenticationPage;
use Gcego\Sentinel\Context\SentinelContextResolver;
use Gcego\Sentinel\Contracts\AuthenticationPanels;
use Gcego\Sentinel\Features\Feature;
use Gcego\Sentinel\Http\Controllers\AccountSecurityController;
use Gcego\Sentinel\Http\Controllers\AssetController;
use Gcego\Sentinel\Http\Controllers\ConfirmPasswordController;
use Gcego\Sentinel\Http\Controllers\EmailVerificationController;
use Gcego\Sentinel\Http\Controllers\LoginController;
use Gcego\Sentinel\Http\Controllers\LogoutController;
use Gcego\Sentinel\Http\Controllers\NewPasswordController;
use Gcego\Sentinel\Http\Controllers\PanelPageController;
use Gcego\Sentinel\Http\Controllers\PasskeyController;
use Gcego\Sentinel\Http\Controllers\PasswordResetLinkController;
use Gcego\Sentinel\Http\Controllers\RegistrationController;
use Gcego\Sentinel\Http\Controllers\StatusPageController;
use Gcego\Sentinel\Http\Controllers\TwoFactorChallengeController;
use Gcego\Sentinel\Http\Middleware\EnsureAccountIsActive;
use Gcego\Sentinel\Http\Middleware\EnsureRegistrationIsAllowed;
use Gcego\Sentinel\Panels\AuthenticationPanel;
use Gcego\Sentinel\Security\AuthenticationRateLimits;
use Gcego\Sentinel\SentinelConfig;
use Gcego\Sentinel\SentinelManager;
use Gcego\Sentinel\Support\Assets;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

/**
 * Registers every Sentinel route: the authentication endpoints (with the
 * URLs and names Laravel's middleware and the GCEGO packages expect), the
 * account security center, the informational pages, the asset route and
 * each panel's login and register pages.
 *
 * Runs once the application has booted, after its own routes, and never
 * registers a route whose name or method + URI is already taken: such a
 * route is skipped and reported (conflicts()), so an application's own
 * /login keeps working and no URL is served twice.
 *
 * Throttles: password sign-in is throttled inside the controller (layered,
 * failures only); every other unauthenticated or sensitive endpoint uses a
 * named limiter (AuthenticationRateLimits).
 */
final class SentinelRouteRegistrar
{
    /**
     * @var list<array{name: string, uri: string, reason: string}>
     */
    private array $conflicts = [];

    /**
     * @var list<string>
     */
    private array $registered = [];

    public function __construct(
        private readonly SentinelConfig $config,
        private readonly AuthenticationPanels $panels,
        private readonly SentinelManager $sentinel,
    ) {}

    /**
     * @return list<string> the names of the routes registered
     */
    public function register(Router $router): array
    {
        $this->conflicts = [];
        $this->registered = [];

        if (! $this->config->enabled || ! $this->config->routes) {
            return [];
        }

        // Names given after a route was added are only indexed on refresh.
        $router->getRoutes()->refreshNameLookups();
        $guard = $this->sentinel->guardName();
        $web = $this->config->middleware;
        $guest = [...$web, 'guest:'.$guard];
        $auth = [...$web, 'auth:'.$guard, EnsureAccountIsActive::class];
        $features = $this->sentinel->features();
        $files = implode('|', array_map(static fn (string $file): string => preg_quote($file, '/'), array_keys(Assets::Files)));
        $this->add($router, 'GET', SentinelRoutes::Assets, $this->config->path($this->config->assetsPath).'/{file}', AssetController::class, [], ['file' => $files]);

        if ($this->config->pageEnabled(AuthenticationPage::Login)) {
            $this->add($router, 'GET', SentinelRoutes::Login, $this->config->path('login'), [LoginController::class, 'create'], $guest);
        }

        $this->add($router, 'POST', SentinelRoutes::LoginStore, $this->config->path('login'), [LoginController::class, 'store'], $guest);
        $this->add($router, 'POST', SentinelRoutes::Logout, $this->config->path('logout'), LogoutController::class, $web);

        if ($this->config->twoFactor) {
            $this->add($router, 'GET', SentinelRoutes::TwoFactorChallenge, $this->config->path('two-factor-challenge'), [TwoFactorChallengeController::class, 'create'], $guest);
            $this->add($router, 'POST', SentinelRoutes::TwoFactorChallengeStore, $this->config->path('two-factor-challenge'), [TwoFactorChallengeController::class, 'store'], [...$guest, $this->throttle(AuthenticationRateLimits::TwoFactor)]);
        }

        if ($this->config->passwordReset) {
            $this->add($router, 'GET', SentinelRoutes::PasswordRequest, $this->config->path('forgot-password'), [PasswordResetLinkController::class, 'create'], $guest);
            $this->add($router, 'POST', SentinelRoutes::PasswordEmail, $this->config->path('forgot-password'), [PasswordResetLinkController::class, 'store'], [...$guest, $this->throttle(AuthenticationRateLimits::PasswordResetRequest)]);
            $this->add($router, 'GET', SentinelRoutes::PasswordReset, $this->config->path('reset-password/{token}'), [NewPasswordController::class, 'create'], $guest);
            $this->add($router, 'POST', SentinelRoutes::PasswordUpdate, $this->config->path('reset-password'), [NewPasswordController::class, 'store'], [...$guest, $this->throttle(AuthenticationRateLimits::PasswordReset)]);
        }

        if ($this->config->emailVerification) {
            $this->add($router, 'GET', SentinelRoutes::VerificationNotice, $this->config->path('email/verify'), [EmailVerificationController::class, 'notice'], $auth);
            $this->add($router, 'GET', SentinelRoutes::VerificationVerify, $this->config->path('email/verify/{id}/{hash}'), [EmailVerificationController::class, 'verify'], [...$auth, 'signed', $this->throttle(AuthenticationRateLimits::Verification)]);
            $this->add($router, 'POST', SentinelRoutes::VerificationSend, $this->config->path('email/verification-notification'), [EmailVerificationController::class, 'send'], [...$auth, $this->throttle(AuthenticationRateLimits::Verification)]);
        }

        $this->add($router, 'GET', SentinelRoutes::PasswordConfirm, $this->config->path('user/confirm-password'), [ConfirmPasswordController::class, 'show'], $auth);
        $this->add($router, 'POST', SentinelRoutes::PasswordConfirmStore, $this->config->path('user/confirm-password'), [ConfirmPasswordController::class, 'store'], [...$auth, $this->throttle(AuthenticationRateLimits::ConfirmPassword)]);
        $this->add($router, 'GET', SentinelRoutes::PasswordConfirmation, $this->config->path('user/confirmed-password-status'), [ConfirmPasswordController::class, 'status'], $auth);

        $this->add($router, 'GET', SentinelRoutes::Register, $this->config->path('register'), [RegistrationController::class, 'create'], [...$guest, EnsureRegistrationIsAllowed::class]);
        $this->add($router, 'POST', SentinelRoutes::RegisterStore, $this->config->path('register'), [RegistrationController::class, 'store'], [...$guest, $this->throttle(AuthenticationRateLimits::Register), EnsureRegistrationIsAllowed::class]);

        if ($features->enabled(Feature::Passkeys)) {
            $passkeys = $this->throttle(AuthenticationRateLimits::Passkeys);
            $this->add($router, 'GET', SentinelRoutes::PasskeyLoginOptions, $this->config->path('passkeys/login/options'), [PasskeyController::class, 'loginOptions'], [...$guest, $passkeys]);
            $this->add($router, 'POST', SentinelRoutes::PasskeyLogin, $this->config->path('passkeys/login'), [PasskeyController::class, 'login'], [...$guest, $passkeys]);
            $this->add($router, 'GET', SentinelRoutes::PasskeyRegistrationOptions, $this->config->path('user/passkeys/options'), [PasskeyController::class, 'registrationOptions'], [...$auth, $passkeys]);
            $this->add($router, 'POST', SentinelRoutes::PasskeyStore, $this->config->path('user/passkeys'), [PasskeyController::class, 'store'], [...$auth, $passkeys]);
        }

        if ($this->config->pageEnabled(AuthenticationPage::AccountSecurity)) {
            $this->add($router, 'GET', SentinelRoutes::AccountSecurity, $this->config->path($this->config->accountSecurityPath), AccountSecurityController::class, $auth);
        }

        $this->add($router, 'GET', SentinelRoutes::AccountUnavailable, $this->config->path($this->config->accountUnavailablePath), [StatusPageController::class, 'accountUnavailable'], $web);
        $this->add($router, 'GET', SentinelRoutes::AccountRecovery, $this->config->path($this->config->accountRecoveryPath), [StatusPageController::class, 'accountRecovery'], $web);
        $this->add($router, 'GET', SentinelRoutes::SessionExpired, $this->config->path($this->config->sessionExpiredPath), [StatusPageController::class, 'sessionExpired'], $web);
        $this->add($router, 'GET', SentinelRoutes::LoggedOut, $this->config->path($this->config->loggedOutPath), [StatusPageController::class, 'loggedOut'], $web);

        foreach ($this->panels->all() as $panel) {
            $this->addPanel($router, $panel, $guest);
        }

        $router->getRoutes()->refreshNameLookups();
        $router->getRoutes()->refreshActionLookups();

        return $this->registered;
    }

    /**
     * Routes that were not registered because the name or URI was taken.
     *
     * @return list<array{name: string, uri: string, reason: string}>
     */
    public function conflicts(): array
    {
        return $this->conflicts;
    }

    /**
     * @return list<string>
     */
    public function registered(): array
    {
        return $this->registered;
    }

    /**
     * @param  list<string>  $guest
     */
    private function addPanel(Router $router, AuthenticationPanel $panel, array $guest): void
    {
        $where = $panel->isTenant() ? ['tenant' => AuthenticationPanel::TenantKeyPattern] : [];
        $route = $this->add($router, 'GET', SentinelRoutes::panelLogin($panel), str_replace(AuthenticationPanel::TenantPlaceholder, '{tenant}', $panel->loginUri()), [PanelPageController::class, 'login'], $guest, $where);
        $this->tag($route, $panel);

        if ($panel->registration) {
            $route = $this->add($router, 'GET', SentinelRoutes::panelRegister($panel), $panel->registerUri(), [PanelPageController::class, 'register'], $guest, $where);
            $this->tag($route, $panel);
        }
    }

    /**
     * Mark a route as a panel's page (trusted route metadata the context resolver reads).
     */
    private function tag(?Route $route, AuthenticationPanel $panel): void
    {
        $action = $route?->getAction();

        if ($route !== null && is_array($action)) {
            $route->setAction([...$action, SentinelContextResolver::RoutePanel => $panel->id]);
        }
    }

    private function throttle(string $limiter): ?string
    {
        return $this->config->rateLimiters ? 'throttle:'.$limiter : null;
    }

    /**
     * @param  'GET'|'POST'  $method
     * @param  class-string|array{0: class-string, 1: string}  $action
     * @param  list<string|null>  $middleware
     * @param  array<string, string>  $where
     */
    private function add(Router $router, string $method, string $name, string $uri, string|array $action, array $middleware, array $where = []): ?Route
    {
        $routes = $router->getRoutes();
        $normalized = self::normalize($uri);

        if ($routes->hasNamedRoute($name)) {
            $this->conflicts[] = ['name' => $name, 'uri' => $uri, 'reason' => 'name'];

            return null;
        }

        foreach ($routes->getRoutes() as $existing) {
            if (in_array($method, $existing->methods(), true) && self::normalize($existing->uri()) === $normalized && $existing->getDomain() === null) {
                $this->conflicts[] = ['name' => $name, 'uri' => $uri, 'reason' => 'uri'];

                return null;
            }
        }

        $route = ($method === 'GET' ? $router->get($uri, $action) : $router->post($uri, $action))
            ->name($name)
            ->middleware(array_values(array_filter($middleware, static fn (?string $entry): bool => $entry !== null)));

        if ($where !== []) {
            $route->where($where);
        }

        $this->registered[] = $name;

        return $route;
    }

    private static function normalize(string $uri): string
    {
        return (string) preg_replace('/\{[^}]+\}/', '{}', trim($uri, '/'));
    }
}
