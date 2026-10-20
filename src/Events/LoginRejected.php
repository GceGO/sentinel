<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Events;

/**
 * Valid credentials, but the user may not enter the panel (or tenant) they
 * signed in to; they were signed out again. Carries identifiers only.
 */
final readonly class LoginRejected implements SecurityEvent
{
    public function __construct(public string|int $userKey, public string $panel, public ?string $tenant, public string $reason) {}

    public function auditType(): string
    {
        return 'login.panel_denied';
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
        return ['panel' => $this->panel, 'tenant' => $this->tenant, 'reason' => $this->reason];
    }
}
