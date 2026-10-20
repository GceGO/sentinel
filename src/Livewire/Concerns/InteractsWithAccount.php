<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Livewire\Concerns;

use Gcego\Sentinel\Accounts\AccountAccess;
use Gcego\Sentinel\SentinelManager;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Session\Session;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The account a security component acts on is always the signed-in user,
 * read from the guard on every request (never from component state), and
 * only while that account is active.
 */
trait InteractsWithAccount
{
    public function bootInteractsWithAccount(): void
    {
        $user = app(SentinelManager::class)->guard()->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        if (! app(AccountAccess::class)->state($user)->allowsAccess()) {
            throw new AccessDeniedHttpException;
        }
    }

    /**
     * The request's session store.
     */
    protected function currentSession(): Session
    {
        $session = app('session.store');

        return $session instanceof Session ? $session : throw new AuthenticationException;
    }

    protected function account(): Authenticatable
    {
        return app(SentinelManager::class)->guard()->user() ?? throw new AuthenticationException;
    }
}
