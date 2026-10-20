<?php

declare(strict_types=1);

namespace Gcego\Sentinel\MultiFactor;

use Illuminate\Contracts\Cache\Repository;

/**
 * Each authenticator code works once: a time step is claimed atomically
 * (Cache::add) per user, and steps at or before the last accepted one are
 * refused. Use a shared cache store (redis, database, memcached) when
 * running more than one server; the entries only live for the window.
 */
final readonly class TotpReplayGuard
{
    public function __construct(private Repository $cache) {}

    public function claim(string $user, int $step, int $window): bool
    {
        $prefix = 'gcego-sentinel:totp:'.hash('sha256', $user);
        $ttl = (2 * $window + 2) * TotpProvider::Period;
        $last = $this->cache->get($prefix.':last');

        if (is_int($last) && $step <= $last) {
            return false;
        }

        if (! $this->cache->add($prefix.':'.$step, true, $ttl)) {
            return false;
        }

        $this->cache->put($prefix.':last', max($step, is_int($last) ? $last : $step), $ttl);

        return true;
    }
}
