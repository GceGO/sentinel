<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Http\Middleware;

use Closure;
use Gcego\Sentinel\Accounts\AccountAccess;
use Gcego\Sentinel\SentinelManager;
use Illuminate\Http\Request;

/**
 * sentinel.active: ends the session of a suspended or disabled account on
 * its next request (remember-me logins included). Also applied to
 * Livewire updates of Sentinel's components (persistent middleware).
 */
final readonly class EnsureAccountIsActive
{
    public function __construct(private SentinelManager $sentinel, private AccountAccess $accounts) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $user = $this->sentinel->guard()->user();

        if ($user !== null) {
            $state = $this->accounts->state($user);

            if (! $state->allowsAccess()) {
                return $this->accounts->endSession($request, $this->sentinel->guardName(), $user, $state, 'request');
            }
        }

        return $next($request);
    }
}
