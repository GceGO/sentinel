<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Events;

/**
 * A user changed their sign-in email address (re-verification started when required).
 */
final readonly class EmailChanged implements SecurityEvent
{
    public function __construct(public string|int $userKey, public bool $verificationRequired) {}

    public function auditType(): string
    {
        return 'email.changed';
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
        return ['verification_required' => $this->verificationRequired];
    }
}
