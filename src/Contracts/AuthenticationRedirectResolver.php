<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Contracts;

use Gcego\Sentinel\Panels\AuthenticationPanel;
use Gcego\Sentinel\Redirects\Destination;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * Where authentication sends people. Every URL it returns is internal and
 * validated (SafeRedirectValidator); a panel destination is only returned
 * when gcego/security allows the user into that panel (and tenant).
 */
interface AuthenticationRedirectResolver
{
    /**
     * After signing in (password, two-factor or passkey): the intended URL, the
     * panel's home, or a rejection when the panel or tenant is not allowed.
     */
    public function afterLogin(Request $request, Authenticatable $user): Destination;

    public function afterRegistration(Request $request, Authenticatable $user): Destination;

    public function afterVerification(Request $request, Authenticatable $user): Destination;

    public function afterPasswordReset(Request $request): string;

    public function afterLogout(Request $request): string;

    /**
     * The login page for a request (a panel's when the path is inside one).
     */
    public function login(Request $request): string;

    /**
     * A panel's login page ("/admin/login", "/institution/42/login").
     */
    public function panelLogin(AuthenticationPanel $panel, ?string $tenant = null): string;

    /**
     * The panel a sign-in in progress is for, without consuming the intended URL.
     */
    public function panelFor(Request $request): ?AuthenticationPanel;

    /**
     * Where signed-in users go when nothing else applies.
     */
    public function home(): string;
}
