<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Events;

/**
 * A user finished signing in (password, then any second factor, or a passkey).
 * $method is "password", "two_factor", "recovery_code" or "passkey".
 */
final readonly class LoginSucceeded implements SecurityEvent
{
    public function __construct(public string|int $userKey, public string $guard, public string $method, public bool $remember) {}

    public function auditType(): string
    {
        return 'login.succeeded';
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
        return ['guard' => $this->guard, 'method' => $this->method, 'remember' => $this->remember];
    }
}
