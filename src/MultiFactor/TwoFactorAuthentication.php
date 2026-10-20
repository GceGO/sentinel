<?php

declare(strict_types=1);

namespace Gcego\Sentinel\MultiFactor;

use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Gcego\Sentinel\Events\RecoveryCodesRegenerated;
use Gcego\Sentinel\Events\RecoveryCodeUsed;
use Gcego\Sentinel\Events\TwoFactorDisabled;
use Gcego\Sentinel\Events\TwoFactorEnabled;
use Gcego\Sentinel\Exceptions\AuthenticationConfigurationException;
use Gcego\Sentinel\Notifications\SecurityNotifier;
use Gcego\Sentinel\SentinelConfig;
use Gcego\Sentinel\Support\UserKey;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * TOTP two-factor authentication on the user's columns
 * (two_factor_secret, encrypted with APP_KEY, and
 * two_factor_confirmed_at):
 *
 *  - begin(): a new secret, not active yet;
 *  - confirm(): a valid code activates it and returns the first recovery codes;
 *  - verify(): a code at sign-in, each code accepted once (TotpReplayGuard);
 *  - redeemRecoveryCode(): a single-use recovery code;
 *  - disable(): removes the secret and every recovery code.
 *
 * Two-factor authentication is only "enrolled" once confirmed. Callers
 * enforce password confirmation before begin(), disable() and
 * regenerateRecoveryCodes().
 */
final readonly class TwoFactorAuthentication
{
    public function __construct(
        private TotpProvider $totp,
        private TotpReplayGuard $replays,
        private RecoveryCodes $recoveryCodes,
        private Encrypter $encrypter,
        private SentinelConfig $config,
        private Repository $settings,
        private Dispatcher $events,
        private SecurityNotifier $notifier,
    ) {}

    public function enrolled(Authenticatable $user): bool
    {
        return $this->hasSecret($user) && data_get($user, 'two_factor_confirmed_at') !== null;
    }

    public function pending(Authenticatable $user): bool
    {
        return $this->hasSecret($user) && data_get($user, 'two_factor_confirmed_at') === null;
    }

    /**
     * Start (or restart) enrollment with a new secret.
     */
    public function begin(Authenticatable $user): void
    {
        $model = self::model($user);

        DB::transaction(function () use ($model, $user): void {
            $model->forceFill([
                'two_factor_secret' => $this->encrypter->encrypt($this->totp->generateSecret()),
                'two_factor_confirmed_at' => null,
            ])->save();
            $this->recoveryCodes->clear($user);
        });
    }

    /**
     * Activate a pending setup with a current code; the first recovery codes, or null for a wrong code.
     *
     * @return list<string>|null
     */
    public function confirm(Authenticatable $user, #[\SensitiveParameter] string $code): ?array
    {
        if (! $this->pending($user) || ! $this->verify($user, $code)) {
            return null;
        }

        $model = self::model($user);
        $codes = DB::transaction(function () use ($model, $user): array {
            $model->forceFill(['two_factor_confirmed_at' => Date::now()])->save();

            return $this->recoveryCodes->generate($user);
        });

        $this->events->dispatch(new TwoFactorEnabled(UserKey::of($user)));
        $this->notifier->send($user, 'two_factor_enabled');

        return $codes;
    }

    /**
     * Whether $code is the user's current authenticator code (each code works once).
     */
    public function verify(Authenticatable $user, #[\SensitiveParameter] string $code): bool
    {
        $secret = $this->secret($user);
        $code = (string) preg_replace('/\s+/', '', $code);

        if ($secret === null) {
            return false;
        }

        $step = $this->totp->verify($secret, $code, $this->config->twoFactorWindow);

        return $step !== null && $this->replays->claim((string) UserKey::of($user), $step, $this->config->twoFactorWindow);
    }

    public function redeemRecoveryCode(Authenticatable $user, #[\SensitiveParameter] string $code): bool
    {
        if (! $this->enrolled($user) || ! $this->recoveryCodes->redeem($user, $code)) {
            return false;
        }

        $remaining = $this->recoveryCodes->remaining($user);
        $this->events->dispatch(new RecoveryCodeUsed(UserKey::of($user), $remaining));
        $this->notifier->send($user, 'recovery_code_used', ['remaining' => $remaining]);

        return true;
    }

    /**
     * @return list<string>
     */
    public function regenerateRecoveryCodes(Authenticatable $user): array
    {
        if (! $this->enrolled($user)) {
            return [];
        }

        $codes = $this->recoveryCodes->generate($user);
        $this->events->dispatch(new RecoveryCodesRegenerated(UserKey::of($user), count($codes)));

        return $codes;
    }

    public function remainingRecoveryCodes(Authenticatable $user): int
    {
        return $this->enrolled($user) ? $this->recoveryCodes->remaining($user) : 0;
    }

    /**
     * Turn two-factor authentication off ($administrative: an operator's account recovery).
     */
    public function disable(Authenticatable $user, bool $administrative = false): void
    {
        $model = self::model($user);
        $wasEnrolled = $this->enrolled($user);

        DB::transaction(function () use ($model, $user): void {
            $model->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();
            $this->recoveryCodes->clear($user);
        });

        if ($wasEnrolled) {
            $this->events->dispatch(new TwoFactorDisabled(UserKey::of($user), $administrative));
            $this->notifier->send($user, 'two_factor_disabled');
        }
    }

    /**
     * The setup key, only while the setup is pending.
     */
    public function setupKey(Authenticatable $user): ?string
    {
        return $this->pending($user) ? $this->secret($user) : null;
    }

    /**
     * The otpauth:// QR code as inline SVG, only while the setup is pending.
     */
    public function qrCodeSvg(Authenticatable $user): ?string
    {
        $secret = $this->setupKey($user);

        if ($secret === null) {
            return null;
        }

        $account = data_get($user, $this->config->identifier);
        $url = $this->totp->otpauthUrl($this->issuer(), is_scalar($account) ? (string) $account : (string) UserKey::of($user), $secret);
        $svg = (new Writer(new ImageRenderer(
            new RendererStyle(192, 0, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(17, 24, 39))),
            new SvgImageBackEnd,
        )))->writeString($url);

        return trim(substr($svg, (int) strpos($svg, "\n") + 1));
    }

    public function issuer(): string
    {
        $name = $this->config->twoFactorIssuer ?? $this->config->branding->name ?? $this->settings->get('app.name');

        return is_string($name) && $name !== '' ? $name : 'GCEGO';
    }

    private function hasSecret(Authenticatable $user): bool
    {
        $secret = data_get($user, 'two_factor_secret');

        return is_string($secret) && $secret !== '';
    }

    private function secret(Authenticatable $user): ?string
    {
        $encrypted = data_get($user, 'two_factor_secret');

        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            $secret = $this->encrypter->decrypt($encrypted);
        } catch (Throwable) {
            // A secret encrypted with a key this application no longer has: fail closed.
            return null;
        }

        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    private static function model(Authenticatable $user): Model
    {
        return $user instanceof Model ? $user : throw AuthenticationConfigurationException::invalid('auth.providers.users.model', 'two-factor authentication needs an Eloquent user model with two_factor_secret and two_factor_confirmed_at columns.');
    }
}
