<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Accounts;

/**
 * The state of an account. Only Active accounts can sign in:
 *
 *  - Suspended: temporarily restricted (the application's status attribute);
 *  - Disabled: closed;
 *  - Review: pending a security review (the application's status attribute);
 *  - Held: an administrator placed a Sentinel security hold (SecurityHolds).
 *
 * Pending email verification is not a state: such users can sign in to
 * verify, and the "verified" middleware keeps them out of protected areas.
 * Account states never replace authorization (gcego/security).
 */
enum AccountState: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Disabled = 'disabled';
    case Review = 'review';
    case Held = 'held';

    public function allowsAccess(): bool
    {
        return $this === self::Active;
    }
}
