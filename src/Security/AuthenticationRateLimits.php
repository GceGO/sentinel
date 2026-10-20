<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Security;

use Gcego\Sentinel\Context\SessionKeys;
use Gcego\Sentinel\SentinelConfig;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Named limiters on Laravel's RateLimiter (no other storage) for every
 * Sentinel endpoint except password sign-in (LoginThrottle). Limits come
 * from gcego-sentinel.rate_limits as [attempts, decay seconds]. Keys hash
 * submitted addresses, so no email address is stored in cache keys; use a
 * shared cache store when running more than one server.
 */
final readonly class AuthenticationRateLimits
{
    public const string TwoFactor = 'gcego-sentinel-two-factor';

    public const string PasswordResetRequest = 'gcego-sentinel-password-reset-request';

    public const string PasswordReset = 'gcego-sentinel-password-reset';

    public const string Verification = 'gcego-sentinel-verification';

    public const string ConfirmPassword = 'gcego-sentinel-confirm-password';

    public const string Register = 'gcego-sentinel-register';

    public const string Passkeys = 'gcego-sentinel-passkeys';

    public function __construct(private RateLimiter $limiter, private SentinelConfig $config) {}

    public function register(): void
    {
        $this->limiter->for(self::TwoFactor, fn (Request $request): Limit => $this->limit('two_factor', 'two-factor|'.self::pending($request)));

        $this->limiter->for(self::PasswordResetRequest, fn (Request $request): array => [
            $this->limit('password_reset_request', 'reset-request|'.self::identity($request, 'email')),
            $this->limit('password_reset_request_ip', 'reset-request-ip|'.$request->ip()),
        ]);

        $this->limiter->for(self::PasswordReset, fn (Request $request): Limit => $this->limit('password_reset', 'reset|'.self::identity($request, 'email')));

        $this->limiter->for(self::Verification, fn (Request $request): Limit => $this->limit('verification', 'verification|'.self::user($request)));

        $this->limiter->for(self::ConfirmPassword, fn (Request $request): Limit => $this->limit('confirm_password', 'confirm|'.self::user($request)));

        $this->limiter->for(self::Register, fn (Request $request): array => [
            $this->limit('register', 'register|'.$request->ip()),
            $this->limit('register_ip', 'register-hour|'.$request->ip()),
        ]);

        $this->limiter->for(self::Passkeys, fn (Request $request): Limit => $this->limit('passkeys', 'passkeys|'.($request->user() !== null ? self::user($request) : $request->ip())));
    }

    private function limit(string $name, string $key): Limit
    {
        [$attempts, $decay] = $this->config->rateLimit($name);

        return Limit::perSecond($attempts, $decay)->by('gcego-sentinel|'.$key);
    }

    private static function identity(Request $request, string $field): string
    {
        $value = $request->input($field);

        return hash('sha256', Str::transliterate(Str::lower(is_string($value) ? trim($value) : '')).'|'.$request->ip());
    }

    private static function user(Request $request): string
    {
        $key = $request->user()?->getAuthIdentifier();

        return is_scalar($key) ? 'user:'.$key : 'ip:'.$request->ip();
    }

    private static function pending(Request $request): string
    {
        $pending = $request->hasSession() ? $request->session()->get(SessionKeys::PendingLogin) : null;
        $user = is_array($pending) ? ($pending['user'] ?? null) : null;

        return is_scalar($user) ? 'user:'.$user : 'ip:'.$request->ip();
    }
}
