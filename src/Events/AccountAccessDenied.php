<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Events;

use Gcego\Sentinel\Accounts\AccountState;

/**
 * A suspended or disabled account was refused sign-in or signed out.
 * Carries the user's key and state only (no credentials, no session data).
 */
final readonly class AccountAccessDenied implements SecurityEvent
{
    public function __construct(
        public string|int $userKey,
        public AccountState $state,
        public string $guard,
        public string $stage,
    ) {}

    public function auditType(): string
    {
        return 'account.access_denied';
    }

    public function auditUserKey(): string|int
    {
        return $this->userKey;
    }

    public function auditOutcome(): string
    {
        return 'denied';
    }

    public function auditContext(): array
    {
        return ['guard' => $this->guard, 'state' => $this->state->value, 'stage' => $this->stage];
    }
}
