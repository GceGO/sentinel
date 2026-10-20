<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Sessions;

use DateTimeImmutable;

/**
 * One stored session of the current user. $id is a SHA-256 digest of the
 * session id: the real id is a bearer credential and never leaves the server.
 */
final readonly class BrowserSession
{
    public function __construct(
        public string $id,
        public UserAgent $agent,
        public ?string $ipAddress,
        public DateTimeImmutable $lastActive,
        public bool $current,
    ) {}
}
