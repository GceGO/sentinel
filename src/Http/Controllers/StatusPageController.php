<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Http\Controllers;

use Gcego\Sentinel\Accounts\AccountState;
use Gcego\Sentinel\AuthenticationPage;
use Gcego\Sentinel\Context\SessionKeys;
use Gcego\Sentinel\SentinelManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Informational pages: account unavailable (after a suspended, disabled,
 * held or under-review account was signed out), account recovery (what to
 * do without your second factor), session expired and signed out. They hold no
 * account data: the state comes from a one-time flash value.
 */
final readonly class StatusPageController
{
    public function __construct(private SentinelManager $sentinel) {}

    public function accountUnavailable(Request $request): Response
    {
        $value = $request->session()->get(SessionKeys::AccountState);
        $state = is_string($value) ? AccountState::tryFrom($value) : null;

        return $this->sentinel->page($request, AuthenticationPage::AccountUnavailable, ['state' => in_array($state, [AccountState::Disabled, AccountState::Review, AccountState::Held], true) ? $state : AccountState::Suspended]);
    }

    public function accountRecovery(Request $request): Response
    {
        return $this->sentinel->page($request, AuthenticationPage::AccountRecovery);
    }

    public function sessionExpired(Request $request): Response
    {
        return $this->sentinel->page($request, AuthenticationPage::SessionExpired);
    }

    public function loggedOut(Request $request): Response
    {
        return $this->sentinel->page($request, AuthenticationPage::LoggedOut);
    }
}
