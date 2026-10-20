<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Passkeys;

use Gcego\Sentinel\Context\SessionKeys;
use Gcego\Sentinel\Events\PasskeyRegistered;
use Gcego\Sentinel\Events\PasskeyRemoved;
use Gcego\Sentinel\Notifications\SecurityNotifier;
use Gcego\Sentinel\SentinelConfig;
use Gcego\Sentinel\Support\RequestMemo;
use Gcego\Sentinel\Support\UserKey;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Actions\StorePasskey;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;
use Laravel\Passkeys\Support\WebAuthn;
use Throwable;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;

/**
 * Passkeys (WebAuthn) through laravel/passkeys and web-auth/webauthn-lib,
 * which verify the challenge, origin (passkeys.allowed_origins), RP ID
 * (passkeys.relying_party_id), user verification and signature counter.
 * Sentinel only orchestrates:
 *
 *  - each ceremony's options live in the session, are pulled (single use)
 *    and expire with the WebAuthn timeout;
 *  - registration needs a signed-in user who recently confirmed their
 *    password (enforced by the caller) and is capped per user;
 *  - a verified login is returned to Sentinel's login pipeline, which
 *    applies account states, panel access and two-factor policy.
 *
 * No cryptographic verification happens here.
 */
final readonly class PasskeyAuthentication
{
    public function __construct(
        private Container $container,
        private SentinelConfig $config,
        private Repository $settings,
        private Dispatcher $events,
        private SecurityNotifier $notifier,
    ) {}

    /**
     * laravel/passkeys is installed and the guard's user model implements PasskeyUser.
     */
    public function supported(): bool
    {
        return PasskeySupport::supported($this->settings, $this->config->guard);
    }

    /**
     * @return class-string|null
     */
    public function userModel(): ?string
    {
        return PasskeySupport::userModel($this->settings, $this->config->guard);
    }

    /**
     * How many passkeys the user has (looked up once per request).
     */
    public function count(Authenticatable $user): int
    {
        return RequestMemo::remember('passkeys.'.UserKey::of($user), static function () use ($user): int {
            try {
                return $user instanceof PasskeyUser ? $user->passkeys()->count() : 0;
            } catch (Throwable) {
                return 0;
            }
        });
    }

    /**
     * @return Collection<int, Passkey>
     */
    public function list(Authenticatable $user): Collection
    {
        if (! $user instanceof PasskeyUser) {
            return new Collection;
        }

        /** @var Collection<int, Passkey> */
        return $user->passkeys()->orderByDesc('created_at')->get(['id', 'user_id', 'name', 'credential', 'last_used_at', 'created_at']);
    }

    /**
     * @return array<array-key, mixed> options for navigator.credentials.create()
     */
    public function registrationOptions(Session $session, Authenticatable $user): array
    {
        $user = self::passkeyUser($user);

        if ($this->count($user) >= $this->config->maxPasskeys) {
            throw ValidationException::withMessages(['passkey' => [__('gcego-sentinel::messages.passkeys.limit', ['max' => $this->config->maxPasskeys])]]);
        }

        $options = $this->container->make(GenerateRegistrationOptions::class)($user);
        $this->remember($session, 'registration', WebAuthn::toJson($options));

        return WebAuthn::toBrowserArray($options);
    }

    /**
     * @param  array<array-key, mixed>  $credential  the browser's PublicKeyCredential as JSON
     *
     * @throws ValidationException
     */
    public function register(Session $session, Authenticatable $user, string $name, array $credential): Passkey
    {
        $user = self::passkeyUser($user);
        $options = $this->pull($session, 'registration', PublicKeyCredentialCreationOptions::class);

        if ($this->count($user) >= $this->config->maxPasskeys) {
            throw ValidationException::withMessages(['passkey' => [__('gcego-sentinel::messages.passkeys.limit', ['max' => $this->config->maxPasskeys])]]);
        }

        try {
            $passkey = $this->container->make(StorePasskey::class)($user, $name, self::credential($credential, 'failed'), $options);
        } catch (InvalidPasskeyException) {
            throw ValidationException::withMessages(['passkey' => [__('gcego-sentinel::messages.passkeys.failed')]]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages(['passkey' => [__('gcego-sentinel::messages.passkeys.failed')]]);
        }

        RequestMemo::forget('passkeys.'.UserKey::of($user));
        $this->events->dispatch(new PasskeyRegistered(UserKey::of($user), self::key($passkey)));
        $this->notifier->send($user, 'passkey_registered');

        return $passkey;
    }

    /**
     * @return array<array-key, mixed> options for navigator.credentials.get()
     */
    public function loginOptions(Session $session): array
    {
        $options = $this->container->make(GenerateVerificationOptions::class)();
        $this->remember($session, 'login', WebAuthn::toJson($options));

        return WebAuthn::toBrowserArray($options);
    }

    /**
     * Verify a sign-in assertion; the passkey (with its user) on success.
     *
     * @param  array<array-key, mixed>  $credential
     *
     * @throws ValidationException
     */
    public function verifyLogin(Session $session, array $credential): Passkey
    {
        $options = $this->pull($session, 'login', PublicKeyCredentialRequestOptions::class);

        try {
            return $this->container->make(VerifyPasskey::class)(self::credential($credential, 'not_recognized'), $options);
        } catch (InvalidPasskeyException) {
            throw ValidationException::withMessages(['passkey' => [__('gcego-sentinel::messages.passkeys.not_recognized')]]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages(['passkey' => [__('gcego-sentinel::messages.passkeys.not_recognized')]]);
        }
    }

    /**
     * Remove one of the user's own passkeys (never another user's).
     */
    public function remove(Authenticatable $user, string|int $id): bool
    {
        if (! $user instanceof PasskeyUser) {
            return false;
        }

        $passkey = $user->passkeys()->whereKey($id)->first();

        if (! $passkey instanceof Passkey || $passkey->delete() !== true) {
            return false;
        }

        RequestMemo::forget('passkeys.'.UserKey::of($user));
        $this->events->dispatch(new PasskeyRemoved(UserKey::of($user), self::key($passkey)));
        $this->notifier->send($user, 'passkey_removed');

        return true;
    }

    private function remember(Session $session, string $ceremony, string $options): void
    {
        $session->put(SessionKeys::PasskeyOptions, [
            'ceremony' => $ceremony,
            'options' => $options,
            'expires_at' => Date::now()->getTimestamp() + (int) ceil(Passkeys::timeout() / 1000) + 30,
        ]);
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return T
     *
     * @throws ValidationException
     */
    private function pull(Session $session, string $ceremony, string $class): object
    {
        $stored = $session->pull(SessionKeys::PasskeyOptions);
        $expired = ! is_array($stored) || ($stored['ceremony'] ?? null) !== $ceremony || ! is_string($stored['options'] ?? null) || ! is_int($stored['expires_at'] ?? null) || $stored['expires_at'] < Date::now()->getTimestamp();

        if ($expired) {
            throw ValidationException::withMessages(['passkey' => [__('gcego-sentinel::messages.passkeys.expired')]]);
        }

        try {
            return WebAuthn::fromJson($stored['options'], $class);
        } catch (Throwable) {
            throw ValidationException::withMessages(['passkey' => [__('gcego-sentinel::messages.passkeys.expired')]]);
        }
    }

    /**
     * @param  array<array-key, mixed>  $credential
     *
     * @throws ValidationException
     */
    private static function credential(array $credential, string $message): PublicKeyCredential
    {
        try {
            $json = json_encode($credential, JSON_THROW_ON_ERROR);

            return WebAuthn::fromJson($json, PublicKeyCredential::class);
        } catch (Throwable) {
            throw ValidationException::withMessages(['passkey' => [__('gcego-sentinel::messages.passkeys.'.$message)]]);
        }
    }

    private static function passkeyUser(Authenticatable $user): PasskeyUser
    {
        return $user instanceof PasskeyUser ? $user : throw ValidationException::withMessages(['passkey' => [__('gcego-sentinel::messages.passkeys.unsupported')]]);
    }

    private static function key(Passkey $passkey): string|int
    {
        $key = $passkey->getKey();

        return is_int($key) || is_string($key) ? $key : 0;
    }
}
