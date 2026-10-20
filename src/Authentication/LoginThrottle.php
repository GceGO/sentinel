<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Authentication;

use Gcego\Sentinel\SentinelConfig;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Layered sign-in throttling on Laravel's RateLimiter (shared cache store):
 *
 *  - identifier + address (rate_limits.login): the tight limit, so one
 *    attacker cannot keep guessing one account;
 *  - address (rate_limits.login_ip): credential stuffing across many
 *    accounts from one client;
 *  - identifier alone (rate_limits.login_account): distributed guessing
 *    against one account. Kept loose and short-lived, because any limit on
 *    the identifier alone lets strangers delay the owner.
 *
 * Only failures count. Keys hash the identifier, so no address is stored in
 * cache keys. A success clears the identifier limits (not the address one).
 */
final readonly class LoginThrottle
{
    public function __construct(private RateLimiter $limiter, private SentinelConfig $config) {}

    /**
     * Seconds until another attempt is allowed, or null when allowed now.
     */
    public function availableIn(string $identifier, ?string $ip): ?int
    {
        if (! $this->config->rateLimiters) {
            return null;
        }

        foreach ($this->limits($identifier, $ip) as [$key, $attempts]) {
            if ($this->limiter->tooManyAttempts($key, $attempts)) {
                return max(1, $this->limiter->availableIn($key));
            }
        }

        return null;
    }

    public function hit(string $identifier, ?string $ip): void
    {
        if (! $this->config->rateLimiters) {
            return;
        }

        foreach ($this->limits($identifier, $ip) as [$key, , $decay]) {
            $this->limiter->hit($key, $decay);
        }
    }

    public function clear(string $identifier, ?string $ip): void
    {
        foreach (array_slice($this->limits($identifier, $ip), 0, 1) as [$key]) {
            $this->limiter->clear($key);
        }

        $this->limiter->clear(self::accountKey($identifier));
    }

    /**
     * Failed sign-ins recorded for this account's identifier (risk signal).
     */
    public function recentAccountFailures(Authenticatable $user): int
    {
        $identifier = data_get($user, $this->config->identifier);

        $attempts = is_scalar($identifier) ? $this->limiter->attempts(self::accountKey(Identifier::normalize((string) $identifier, $this->config->lowercaseIdentifier))) : 0;

        return is_numeric($attempts) ? (int) $attempts : 0;
    }

    /**
     * @return list<array{0: string, 1: int, 2: int}> [key, attempts, decay]
     */
    private function limits(string $identifier, ?string $ip): array
    {
        [$pairAttempts, $pairDecay] = $this->config->rateLimit('login');
        [$ipAttempts, $ipDecay] = $this->config->rateLimit('login_ip');
        [$accountAttempts, $accountDecay] = $this->config->rateLimit('login_account');

        return [
            ['gcego-sentinel:login:'.hash('sha256', $identifier.'|'.$ip), $pairAttempts, $pairDecay],
            ['gcego-sentinel:login-ip:'.hash('sha256', (string) $ip), $ipAttempts, $ipDecay],
            [self::accountKey($identifier), $accountAttempts, $accountDecay],
        ];
    }

    private static function accountKey(string $identifier): string
    {
        return 'gcego-sentinel:login-account:'.hash('sha256', $identifier);
    }
}
