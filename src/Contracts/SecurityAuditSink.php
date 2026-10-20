<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Contracts;

use Gcego\Sentinel\Audit\AuditEntry;

/**
 * Receives Sentinel's security audit entries (identifiers, outcomes and
 * minimised network metadata; never secrets). Implementations must not
 * throw and should not block the request for long.
 */
interface SecurityAuditSink
{
    public function record(AuditEntry $entry): void;
}
