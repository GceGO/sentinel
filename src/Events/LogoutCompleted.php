<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Events;

/**
 * A user signed out ($everywhere: every session of the account ended).
 */
final readonly class LogoutCompleted implements SecurityEvent
{
    public function __construct(public string|int $userKey, public string $guard, public bool $everywhere = false) {}

    public function auditType(): string
    {
        return 'logout.completed';
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
        return ['guard' => $this->guard, 'everywhere' => $this->everywhere];
    }
}
