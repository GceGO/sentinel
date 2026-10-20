<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Diagnostics;

use Gcego\Core\Diagnostics\DiagnosticReport;
use Gcego\Core\Diagnostics\DiagnosticResult;
use Gcego\Sentinel\Contracts\AuthenticationPanels;
use Gcego\Sentinel\Features\Feature;
use Gcego\Sentinel\Passkeys\PasskeyAuthentication;
use Gcego\Sentinel\Passkeys\PasskeySupport;
use Gcego\Sentinel\Routing\SentinelRouteRegistrar;
use Gcego\Sentinel\Security\AuthenticationRateLimits;
use Gcego\Sentinel\SentinelConfig;
use Gcego\Sentinel\SentinelManager;
use Gcego\Sentinel\SentinelServiceProvider;
use Gcego\Sentinel\Support\Assets;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Builder;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Read-only report of the authentication setup: Sentinel, features, guard
 * and broker, routes and conflicts, tables, panels, session cookies, cache
 * store, hashing, two-factor, passkeys, assets, rate limiters, audit trail
 * and translations. Results carry names,
 * counts and flags only, never configuration values that could be secrets
 * (keys, passwords, tokens).
 */
final readonly class SentinelInspector
{
    public function __construct(
        private Container $container,
        private SentinelConfig $config,
        private Repository $settings,
    ) {}

    public function inspect(): DiagnosticReport
    {
        $checks = [];

        foreach (['installation', 'features', 'guard', 'broker', 'routes', 'database', 'panels', 'session', 'cache', 'hashing', 'twoFactor', 'passkeys', 'assets', 'rateLimiters', 'audit', 'localization'] as $check) {
            try {
                $checks[] = $this->{$check}();
            } catch (Throwable $exception) {
                $checks[] = DiagnosticResult::failed('sentinel.'.$check, 'The check could not run ('.class_basename($exception).').');
            }
        }

        return new DiagnosticReport(...$checks);
    }

    private function installation(): DiagnosticResult
    {
        return $this->config->enabled
            ? DiagnosticResult::passed('sentinel.installation', 'Sentinel is enabled.', ['layout' => $this->config->layout, 'theme' => $this->config->theme])
            : DiagnosticResult::warning('sentinel.installation', 'Sentinel is disabled (gcego-sentinel.enabled).');
    }

    private function features(): DiagnosticResult
    {
        $available = array_map(static fn (Feature $feature): string => $feature->value, $this->container->make(SentinelManager::class)->features()->available());

        return DiagnosticResult::passed('sentinel.features', count($available).' authentication features available.', ['available' => $available]);
    }

    private function guard(): DiagnosticResult
    {
        $guard = $this->container->make(SentinelManager::class)->guardName();
        $driver = $this->settings->get('auth.guards.'.$guard.'.driver');

        if ($driver === null) {
            return DiagnosticResult::failed('sentinel.guard', sprintf('The "%s" guard is not configured.', $guard), ['guard' => $guard]);
        }

        if ($driver !== 'session') {
            return DiagnosticResult::failed('sentinel.guard', sprintf('The "%s" guard uses the "%s" driver; Sentinel needs a session guard.', $guard, is_string($driver) ? $driver : '?'), ['guard' => $guard]);
        }

        return DiagnosticResult::passed('sentinel.guard', sprintf('Sessions on the "%s" guard.', $guard), ['guard' => $guard]);
    }

    private function broker(): DiagnosticResult
    {
        $broker = $this->config->passwordBroker;

        if (! is_array($this->settings->get('auth.passwords.'.$broker))) {
            return $this->config->passwordReset
                ? DiagnosticResult::failed('sentinel.broker', sprintf('The "%s" password broker is not configured but password resets are enabled.', $broker), ['broker' => $broker])
                : DiagnosticResult::warning('sentinel.broker', sprintf('The "%s" password broker is not configured.', $broker), ['broker' => $broker]);
        }

        $expire = $this->settings->get('auth.passwords.'.$broker.'.expire');
        $details = ['broker' => $broker, 'expire_minutes' => is_int($expire) ? $expire : null];

        if (is_int($expire) && $expire > 60) {
            return DiagnosticResult::warning('sentinel.broker', sprintf('Password reset links stay valid for %d minutes; 60 or less is recommended.', $expire), $details);
        }

        return DiagnosticResult::passed('sentinel.broker', sprintf('Password broker "%s".', $broker), $details);
    }

    private function routes(): DiagnosticResult
    {
        $routes = $this->container->make(Router::class)->getRoutes();
        $missing = array_values(array_filter(['login', 'login.store', 'logout'], static fn (string $name): bool => ! $routes->hasNamedRoute($name)));
        $conflicts = $this->container->make(SentinelRouteRegistrar::class)->conflicts();
        $details = ['missing' => $missing, 'skipped' => array_map(static fn (array $conflict): string => $conflict['name'], $conflicts)];

        if ($missing !== []) {
            return DiagnosticResult::failed('sentinel.routes', 'Authentication routes are missing: '.implode(', ', $missing).'.', $details);
        }

        if ($conflicts !== []) {
            return DiagnosticResult::warning('sentinel.routes', count($conflicts).' Sentinel route(s) were not registered because the name or URL is taken (another authentication package?).', $details);
        }

        return DiagnosticResult::passed('sentinel.routes', 'Authentication routes are registered without conflicts.', $details);
    }

    private function panels(): DiagnosticResult
    {
        $panels = $this->container->make(AuthenticationPanels::class)->all();
        $open = array_keys(array_filter($panels, static fn ($panel): bool => $panel->permission === null && ! $panel->isTenant()));
        $registration = array_keys(array_filter($panels, static fn ($panel): bool => $panel->registration));
        $details = ['panels' => array_keys($panels), 'without_permission' => $open, 'registration' => $registration];

        if ($open !== [] || $registration !== []) {
            return DiagnosticResult::warning('sentinel.panels', 'Some panels admit every signed-in user or allow registration; check that this is intended.', $details);
        }

        return DiagnosticResult::passed('sentinel.panels', count($panels).' panel(s) with authentication pages.', $details);
    }

    private function session(): DiagnosticResult
    {
        $production = $this->container instanceof Application && $this->container->environment('production');
        $secure = $this->settings->get('session.secure');
        $sameSite = $this->settings->get('session.same_site');
        $details = [
            'driver' => is_string($driver = $this->settings->get('session.driver')) ? $driver : null,
            'secure' => $secure === true,
            'http_only' => $this->settings->get('session.http_only', true) === true,
            'same_site' => is_string($sameSite) ? $sameSite : null,
            'browser_sessions' => $driver === 'database' && $this->config->browserSessions,
        ];

        if ($this->settings->get('session.http_only', true) !== true) {
            return DiagnosticResult::failed('sentinel.session', 'Session cookies are readable by scripts (session.http_only is false).', $details);
        }

        if ($production && $secure !== true) {
            return DiagnosticResult::failed('sentinel.session', 'Session cookies are not limited to HTTPS in production (session.secure).', $details);
        }

        if (! in_array(is_string($sameSite) ? strtolower($sameSite) : null, ['lax', 'strict'], true)) {
            return DiagnosticResult::warning('sentinel.session', 'session.same_site is not "lax" or "strict".', $details);
        }

        return DiagnosticResult::passed('sentinel.session', 'Session cookies are HTTP-only'.($secure === true ? ', HTTPS-only' : '').' and same-site.', $details);
    }

    private function twoFactor(): DiagnosticResult
    {
        $required = $this->config->twoFactorPermissions !== [] || array_filter($this->config->panels, static fn ($panel): bool => $panel->twoFactor) !== [];

        if (! $this->config->twoFactor) {
            return $required
                ? DiagnosticResult::failed('sentinel.two_factor', 'Two-factor authentication is required for some users but gcego-sentinel.features.two_factor is off.')
                : DiagnosticResult::passed('sentinel.two_factor', 'Two-factor authentication is disabled.', ['enabled' => false]);
        }

        $table = $this->usersTable();
        $columns = $table === null ? [] : array_values(array_filter(['two_factor_secret', 'two_factor_confirmed_at'], fn (string $column): bool => ! $this->schema()->hasColumn($table, $column)));
        $details = ['enabled' => true, 'required' => $required, 'window' => $this->config->twoFactorWindow, 'cache_store' => $this->cacheStore()];

        if ($columns !== []) {
            return DiagnosticResult::failed('sentinel.two_factor', 'The users table lacks '.implode(', ', $columns).' (see docs/sentinel/MFA-AND-PASSKEYS.md).', $details);
        }

        $model = $this->userModel();

        if ($this->config->emailVerification && is_string($model) && ! is_subclass_of($model, MustVerifyEmail::class)) {
            return DiagnosticResult::warning('sentinel.two_factor', 'Two-factor is ready, but email verification is enabled and the user model does not implement MustVerifyEmail (verification does not apply).', $details);
        }

        return DiagnosticResult::passed('sentinel.two_factor', 'TOTP two-factor authentication with single-use recovery codes is available.', $details);
    }

    private function passkeys(): DiagnosticResult
    {
        if (! $this->config->passkeys) {
            return DiagnosticResult::passed('sentinel.passkeys', 'Passkeys are disabled.', ['enabled' => false]);
        }

        $passkeys = $this->container->make(PasskeyAuthentication::class);
        $origins = $this->settings->get('passkeys.allowed_origins', []);
        $origins = is_array($origins) ? array_values(array_filter($origins, is_string(...))) : [];
        $details = ['enabled' => true, 'origins' => count($origins), 'table' => $this->schema()->hasTable('passkeys')];

        if (! $passkeys->supported()) {
            return DiagnosticResult::failed('sentinel.passkeys', 'Passkeys are enabled but the user model does not implement Laravel\\Passkeys\\Contracts\\PasskeyUser (or laravel/passkeys is missing).', $details);
        }

        if (! $details['table']) {
            return DiagnosticResult::failed('sentinel.passkeys', 'The "passkeys" table is missing (php artisan vendor:publish --tag=passkeys-migrations && php artisan migrate).', $details);
        }

        if ($this->production() && array_filter($origins, static fn (string $origin): bool => ! str_starts_with($origin, 'https://')) !== []) {
            return DiagnosticResult::failed('sentinel.passkeys', 'passkeys.allowed_origins must be https:// origins in production.', $details);
        }

        return DiagnosticResult::warning('sentinel.passkeys', 'Passkeys are enabled. They are verified by web-auth/webauthn-lib; test them with your users\' authenticators before relying on them (docs/sentinel/MFA-AND-PASSKEYS.md).', $details);
    }

    private function database(): DiagnosticResult
    {
        $missing = array_values(array_filter(['sentinel_recovery_codes', 'sentinel_security_events', 'sentinel_security_holds', 'sentinel_known_browsers'], fn (string $table): bool => ! $this->schema()->hasTable($table)));

        return $missing === []
            ? DiagnosticResult::passed('sentinel.database', 'Sentinel\'s tables exist.')
            : DiagnosticResult::failed('sentinel.database', 'Run "php artisan migrate": missing '.implode(', ', $missing).'.', ['missing' => $missing]);
    }

    private function cache(): DiagnosticResult
    {
        $store = $this->cacheStore();
        $details = ['store' => $store];

        if ($store === 'array' && $this->production()) {
            return DiagnosticResult::failed('sentinel.cache', 'The "array" cache forgets rate limits, code replays and session revocations between requests.', $details);
        }

        if (in_array($store, ['array', 'file'], true)) {
            return DiagnosticResult::warning('sentinel.cache', sprintf('The "%s" cache store is per-server: use redis, database or memcached when running more than one server.', $store), $details);
        }

        return DiagnosticResult::passed('sentinel.cache', sprintf('Rate limits and replay protection use the shared "%s" cache store.', $store), $details);
    }

    private function hashing(): DiagnosticResult
    {
        $driver = $this->settings->get('hashing.driver', 'bcrypt');
        $rounds = $this->settings->get('hashing.bcrypt.rounds', 12);
        $details = ['driver' => is_string($driver) ? $driver : null];

        if ($driver === 'bcrypt' && is_numeric($rounds) && (int) $rounds < 12 && $this->production()) {
            return DiagnosticResult::warning('sentinel.hashing', 'bcrypt uses fewer than 12 rounds in production (BCRYPT_ROUNDS).', $details + ['rounds' => (int) $rounds]);
        }

        return DiagnosticResult::passed('sentinel.hashing', 'Passwords are hashed with '.(is_string($driver) ? $driver : 'the configured hasher').'.', $details);
    }

    private function assets(): DiagnosticResult
    {
        if ($this->config->assetsMode === 'vite') {
            return DiagnosticResult::passed('sentinel.assets', 'Assets come from the application\'s Vite build.', ['mode' => 'vite', 'entries' => count($this->config->vite)]);
        }

        $assets = $this->container->make(Assets::class);
        $missing = array_values(array_filter(array_keys(Assets::Files), static fn (string $file): bool => $assets->path($file) === null));

        return $missing === []
            ? DiagnosticResult::passed('sentinel.assets', 'The prebuilt stylesheet and script are served by Sentinel.', ['mode' => 'package', 'css_bytes' => (int) filesize((string) $assets->path('sentinel.css'))])
            : DiagnosticResult::failed('sentinel.assets', 'Prebuilt assets are missing: '.implode(', ', $missing).'.', ['mode' => 'package']);
    }

    private function rateLimiters(): DiagnosticResult
    {
        if (! $this->config->rateLimiters) {
            return DiagnosticResult::failed('sentinel.rate_limiters', 'Sentinel\'s rate limits are disabled (gcego-sentinel.rate_limits.enabled).');
        }

        $missing = array_values(array_filter([AuthenticationRateLimits::TwoFactor, AuthenticationRateLimits::PasswordResetRequest, AuthenticationRateLimits::Register], static fn (string $name): bool => RateLimiter::limiter($name) === null));

        return $missing === []
            ? DiagnosticResult::passed('sentinel.rate_limiters', 'Sign-in, two-factor, recovery, verification, confirmation, registration and passkey attempts are rate limited.')
            : DiagnosticResult::failed('sentinel.rate_limiters', 'Named rate limiters are not defined: '.implode(', ', $missing).'.', ['missing' => $missing]);
    }

    private function audit(): DiagnosticResult
    {
        if (! $this->config->audit) {
            return DiagnosticResult::warning('sentinel.audit', 'The security audit trail is disabled (gcego-sentinel.audit.enabled).');
        }

        return DiagnosticResult::passed('sentinel.audit', sprintf('Security events go to the "%s" sink, kept %d days.', $this->config->auditSink, $this->config->auditRetentionDays), ['sink' => $this->config->auditSink, 'ip_addresses' => $this->config->auditIpAddresses]);
    }

    private function localization(): DiagnosticResult
    {
        $directory = dirname(__DIR__, 2).'/resources/lang';
        $locales = array_values(array_filter(scandir($directory) ?: [], static fn (string $entry): bool => $entry[0] !== '.' && is_file($directory.'/'.$entry.'/messages.php')));
        $locale = $this->settings->get('app.locale', 'en');
        $details = ['locales' => $locales, 'namespace' => SentinelServiceProvider::Views];

        return in_array($locale, $locales, true) || ! is_string($locale)
            ? DiagnosticResult::passed('sentinel.localization', 'Sentinel is translated into '.implode(', ', $locales).'.', $details)
            : DiagnosticResult::warning('sentinel.localization', sprintf('No Sentinel translation for the application locale "%s" (English is used).', $locale), $details);
    }

    private function production(): bool
    {
        return $this->container instanceof Application && $this->container->environment('production');
    }

    private function cacheStore(): ?string
    {
        $store = $this->settings->get('cache.default');
        $driver = is_string($store) ? $this->settings->get('cache.stores.'.$store.'.driver') : null;

        return is_string($driver) ? $driver : null;
    }

    /**
     * @return class-string|null
     */
    private function userModel(): ?string
    {
        return PasskeySupport::userModel($this->settings, $this->config->guard);
    }

    private function usersTable(): ?string
    {
        $model = $this->userModel();

        return $model !== null && is_a($model, Model::class, true) ? (new $model)->getTable() : null;
    }

    private function schema(): Builder
    {
        return $this->container->make('db')->connection()->getSchemaBuilder();
    }
}
