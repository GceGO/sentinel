<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Registration;

use Gcego\Sentinel\Contracts\RegistrationPolicy;
use Gcego\Sentinel\Panels\AuthenticationPanel;
use Gcego\Sentinel\SentinelConfig;
use Illuminate\Http\Request;

/**
 * gcego-sentinel.registration.enabled for public registration, and each
 * panel's own "registration" flag (false by default) for its register page.
 */
final readonly class ConfiguredRegistrationPolicy implements RegistrationPolicy
{
    public function __construct(private SentinelConfig $config) {}

    public function allows(Request $request, ?AuthenticationPanel $panel = null): bool
    {
        return $panel === null ? $this->config->registration : $panel->registration;
    }
}
