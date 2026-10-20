<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Audit;

use DateTimeImmutable;

/**
 * One security audit record. $sessionReference is a keyed hash of the
 * session id (it links entries of one session without revealing the id);
 * the IP address is minimised according to gcego-sentinel.audit.ip_addresses.
 */
final readonly class AuditEntry
{
    /**
     * @param  array<string, string|int|bool|null>  $context
     */
    public function __construct(
        public string $type,
        public string $outcome,
        public ?string $userKey,
        public array $context,
        public DateTimeImmutable $occurredAt,
        public ?string $sessionReference = null,
        public ?string $tenant = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
    ) {}

    /**
     * @return array{type: string, outcome: string, user: string|null, context: array<string, string|int|bool|null>, occurred_at: string, session: string|null, tenant: string|null, ip: string|null, user_agent: string|null}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'outcome' => $this->outcome,
            'user' => $this->userKey,
            'context' => $this->context,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
            'session' => $this->sessionReference,
            'tenant' => $this->tenant,
            'ip' => $this->ipAddress,
            'user_agent' => $this->userAgent,
        ];
    }
}
