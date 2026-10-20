<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Events;

/**
 * A passkey was added to the account.
 */
final readonly class PasskeyRegistered implements SecurityEvent
{
    public function __construct(public string|int $userKey, public string|int $passkey) {}

    public function auditType(): string
    {
        return 'passkey.registered';
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
        return ['passkey' => $this->passkey];
    }
}
