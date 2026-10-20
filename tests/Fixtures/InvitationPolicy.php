<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Tests\Fixtures;

use Gcego\Sentinel\Contracts\RegistrationPolicy;
use Gcego\Sentinel\Panels\AuthenticationPanel;
use Illuminate\Http\Request;

/**
 * Invitation-only registration: a valid invitation code in the session.
 */
final class InvitationPolicy implements RegistrationPolicy
{
    public function allows(Request $request, ?AuthenticationPanel $panel = null): bool
    {
        return $request->hasSession() && $request->session()->get('invitation') === 'valid-invitation';
    }
}
