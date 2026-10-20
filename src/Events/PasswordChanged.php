<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Events;

/**
 * A signed-in user changed their password on the account security page.
 * (Password resets fire PasswordResetCompleted and Laravel's PasswordReset.) No password data.
 */
final readonly class PasswordChanged implements SecurityEvent
{
    public function __construct(public string|int $userKey, public string $guard) {}

    public function auditType(): string
    {
        return 'password.changed';
    }

    public function auditUserKey(): string|int
    {
        return $this->userKey;
    }

    public function auditOutcome(): string
    {
        return 'succeeded';
    }

    public function auditContext(): array
    {
        return ['guard' => $this->guard];
    }
}
