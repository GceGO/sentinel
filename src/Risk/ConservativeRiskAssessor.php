<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Risk;

use Gcego\Sentinel\Authentication\LoginThrottle;
use Gcego\Sentinel\Contracts\RiskAssessor;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * Two dependable signals only: a browser this account has not used before,
 * and several failed sign-ins for this account in the last hour. No IP
 * geolocation and no device fingerprinting.
 */
final readonly class ConservativeRiskAssessor implements RiskAssessor
{
    public const int RecentFailures = 3;

    public function __construct(private KnownBrowsers $browsers, private LoginThrottle $throttle) {}

    public function assess(Request $request, Authenticatable $user, string $method): RiskAssessment
    {
        $signals = [];

        if (! $this->browsers->recognizes($request, $user)) {
            $signals[] = 'new_browser';
        }

        if ($this->throttle->recentAccountFailures($user) >= self::RecentFailures) {
            $signals[] = 'recent_failures';
        }

        return new RiskAssessment($signals);
    }
}
