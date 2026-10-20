<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Events;

/**
 * A user signed out other browser sessions. Session ids are never included:
 * $count is how many stored sessions were removed (0 when the driver
 * cannot list them; Laravel's OtherDeviceLogout still invalidates them).
 */
final readonly class BrowserSessionsRevoked implements SecurityEvent
{
    public function __construct(public string|int $userKey, public string $guard, public int $count, public bool $all) {}

    public function auditType(): string
    {
        return 'session.revoked';
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
        return ['guard' => $this->guard, 'count' => $this->count, 'all' => $this->all];
    }
}
