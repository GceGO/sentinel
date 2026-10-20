<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Redirects;

use Gcego\Sentinel\Panels\AuthenticationPanel;

/**
 * Where a signed-in user goes: a validated internal URL, or a rejection
 * (the user may not enter the panel or tenant they asked for).
 */
final readonly class Destination
{
    private function __construct(
        public ?string $url,
        public ?AuthenticationPanel $panel = null,
        public ?string $tenant = null,
        public ?string $rejection = null,
        public bool $twoFactorSetup = false,
        public ?string $continue = null,
    ) {}

    public static function to(string $url, ?AuthenticationPanel $panel = null, ?string $tenant = null): self
    {
        return new self($url, $panel, $tenant);
    }

    /**
     * The user must enroll in two-factor authentication first ($url is the
     * account security page); $continue is where they were going.
     */
    public static function twoFactorSetup(string $url, string $continue, ?AuthenticationPanel $panel = null, ?string $tenant = null): self
    {
        return new self($url, $panel, $tenant, twoFactorSetup: true, continue: $continue);
    }

    /**
     * @param  'panel_denied'|'account_unavailable'  $reason
     */
    public static function rejected(string $reason, ?AuthenticationPanel $panel = null, ?string $tenant = null): self
    {
        return new self(null, $panel, $tenant, $reason);
    }

    public function isRejected(): bool
    {
        return $this->rejection !== null;
    }
}
