<?php

declare(strict_types=1);

namespace Gcego\Sentinel\MultiFactor;

use Illuminate\Support\Facades\Date;
use PragmaRX\Google2FA\Google2FA;
use Throwable;

/**
 * RFC 6238 time-based one-time passwords (6 digits, 30-second steps,
 * HMAC-SHA1: what authenticator apps support) through pragmarx/google2fa.
 * Secrets are 160 bits from random_bytes(). verify() returns the matched
 * time step so the caller can refuse replays; it never says which step
 * matched to the user.
 */
final readonly class TotpProvider
{
    public const int Period = 30;

    public function generateSecret(): string
    {
        return (new Google2FA)->generateSecretKey(32);
    }

    public function otpauthUrl(string $issuer, string $account, string $secret): string
    {
        return (new Google2FA)->getQRCodeUrl($issuer, $account, $secret);
    }

    /**
     * The time step a code matches within ±$window steps of now, or null.
     */
    public function verify(#[\SensitiveParameter] string $secret, #[\SensitiveParameter] string $code, int $window): ?int
    {
        if (preg_match('/^\d{6}$/', $code) !== 1 || $secret === '') {
            return null;
        }

        $google2fa = new Google2FA;
        $now = intdiv(Date::now()->getTimestamp(), self::Period);
        $matched = null;

        try {
            // Every candidate step is compared (constant work whatever matches).
            for ($step = $now - $window; $step <= $now + $window; $step++) {
                if (hash_equals($google2fa->oathTotp($secret, $step), $code) && $matched === null) {
                    $matched = $step;
                }
            }
        } catch (Throwable) {
            return null;
        }

        return $matched;
    }

    public function currentStep(): int
    {
        return intdiv(Date::now()->getTimestamp(), self::Period);
    }

    /**
     * The code for a step (tests and the testing helpers only).
     */
    public function codeAt(#[\SensitiveParameter] string $secret, ?int $step = null): string
    {
        return (new Google2FA)->oathTotp($secret, $step ?? $this->currentStep());
    }
}
