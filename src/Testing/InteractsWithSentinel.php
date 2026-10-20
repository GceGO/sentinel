<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Testing;

use Gcego\Sentinel\MultiFactor\TotpProvider;
use Gcego\Sentinel\MultiFactor\TwoFactorAuthentication;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\Date;

/**
 * Test helpers for applications using Sentinel (Pest or PHPUnit):
 *
 *     uses(InteractsWithSentinel::class);
 *
 *     $codes = $this->enableTwoFactor($user);             // through Sentinel's own setup + confirmation
 *     $this->post('/two-factor-challenge', ['code' => $this->twoFactorCode($user)]);
 *     $this->withConfirmedPassword()->get('/account/security');
 *
 * They use Sentinel's own services, never shortcuts that would hide a
 * broken integration.
 */
trait InteractsWithSentinel
{
    /**
     * Enable two-factor authentication (confirmed with a real code unless
     * $confirmed is false); the recovery codes when confirmed.
     *
     * @return list<string>
     */
    protected function enableTwoFactor(Authenticatable $user, bool $confirmed = true): array
    {
        $twoFactor = app(TwoFactorAuthentication::class);
        $twoFactor->begin($user);

        if (! $confirmed) {
            return [];
        }

        // A step in the past, so the code a test submits next is not a replay.
        $codes = $twoFactor->confirm($user, $this->twoFactorCode($user, -1));

        return $codes ?? [];
    }

    /**
     * The authenticator code of a user with two-factor authentication ($offset: time steps from now).
     */
    protected function twoFactorCode(Authenticatable $user, int $offset = 0): string
    {
        $encrypted = data_get($user, 'two_factor_secret');
        $secret = is_string($encrypted) ? app(Encrypter::class)->decrypt($encrypted) : '';
        $totp = app(TotpProvider::class);

        return $totp->codeAt(is_string($secret) ? $secret : '', $totp->currentStep() + $offset);
    }

    /**
     * Mark the session's password as just confirmed (Laravel's password.confirm state).
     */
    protected function withConfirmedPassword(): static
    {
        return $this->withSession(['auth.password_confirmed_at' => Date::now()->getTimestamp()]);
    }
}
