<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Security;

use Gcego\Sentinel\SentinelConfig;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Date;

/**
 * Step-up verification state: when the session last proved its password
 * again, in Laravel's own key ("auth.password_confirmed_at", shared with
 * the password.confirm middleware). Sensitive operations check it on the
 * server (sentinel.step-up middleware, the account security components);
 * the browser never decides.
 */
final readonly class StepUp
{
    public function __construct(private SentinelConfig $config, private Repository $settings) {}

    public function confirm(Session $session): void
    {
        $session->put('auth.password_confirmed_at', Date::now()->getTimestamp());
    }

    /**
     * Whether the password was confirmed within $seconds (default: the configured timeout).
     */
    public function confirmed(Session $session, ?int $seconds = null): bool
    {
        $confirmedAt = $session->get('auth.password_confirmed_at', 0);

        return is_numeric($confirmedAt) && (Date::now()->getTimestamp() - (int) $confirmedAt) < ($seconds ?? $this->timeout());
    }

    public function timeout(): int
    {
        if ($this->config->confirmationTimeout !== null) {
            return $this->config->confirmationTimeout;
        }

        $timeout = $this->settings->get('auth.password_timeout', 10800);

        return is_numeric($timeout) ? (int) $timeout : 10800;
    }
}
