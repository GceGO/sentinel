<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Audit;

use DateTimeImmutable;
use Gcego\Sentinel\Context\SessionKeys;
use Gcego\Sentinel\Contracts\SecurityAuditSink;
use Gcego\Sentinel\Events\SecurityEvent;
use Gcego\Sentinel\SentinelConfig;
use Gcego\Sentinel\Support\AppKey;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Throwable;

/**
 * Listens to every Sentinel SecurityEvent and hands one AuditEntry to the
 * configured sink, with request metadata minimised by configuration. Only
 * scalar context values from the event are kept, and auditing never breaks
 * the request it observes.
 */
final readonly class SecurityAuditRecorder
{
    public function __construct(private Container $container, private SentinelConfig $config) {}

    public function handle(SecurityEvent $event): void
    {
        if (! $this->config->audit) {
            return;
        }

        try {
            $this->container->make(SecurityAuditSink::class)->record($this->entry($event));
        } catch (Throwable) {
            // Auditing must never deny or break the operation it observes.
        }
    }

    public function entry(SecurityEvent $event): AuditEntry
    {
        $request = $this->container->bound('request') ? $this->container->make('request') : null;
        $request = $request instanceof Request ? $request : null;
        $session = $request?->hasSession() === true ? $request->session() : null;
        $key = $event->auditUserKey();
        $tenant = $session?->get(SessionKeys::Tenant);
        $agent = $this->config->auditUserAgents ? $request?->userAgent() : null;
        $context = [];

        foreach ($event->auditContext() as $name => $value) {
            if (is_string($value)) {
                $context[$name] = mb_substr($value, 0, 190);
            } elseif (is_int($value) || is_bool($value) || $value === null) {
                $context[$name] = $value;
            }
        }

        return new AuditEntry(
            mb_substr($event->auditType(), 0, 64),
            $event->auditOutcome(),
            $key === null ? null : mb_substr((string) $key, 0, 64),
            $context,
            new DateTimeImmutable,
            $session !== null && $session->getId() !== '' ? self::reference($session->getId()) : null,
            is_string($tenant) ? mb_substr($tenant, 0, 100) : null,
            NetworkAddress::minimise($request?->ip(), $this->config->auditIpAddresses),
            is_string($agent) && $agent !== '' ? mb_substr($agent, 0, 255) : null,
        );
    }

    /**
     * A keyed, one-way reference to a session id.
     */
    public static function reference(string $sessionId): string
    {
        return substr(hash_hmac('sha256', 'gcego-sentinel:session|'.$sessionId, AppKey::get()), 0, 40);
    }
}
