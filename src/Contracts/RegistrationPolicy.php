<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Contracts;

use Gcego\Sentinel\Panels\AuthenticationPanel;
use Illuminate\Http\Request;

/**
 * Whether a visitor may register, for the public site (no panel) or a
 * panel's register page. Checked on the page and again, server-side, on
 * the registration endpoint. Invitation-only or staff registration
 * is a policy that checks the request (a signed invitation, a domain...).
 *
 * Registration never grants roles: what the new account may do is decided
 * by the CreatesAccounts implementation and gcego/security.
 */
interface RegistrationPolicy
{
    public function allows(Request $request, ?AuthenticationPanel $panel = null): bool;
}
