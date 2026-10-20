<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Authentication;

use Gcego\Sentinel\Events\LoginFailed;
use Gcego\Sentinel\SentinelConfig;
use Gcego\Sentinel\SentinelManager;
use Gcego\Sentinel\Support\UserKey;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Validated;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Validation\ValidationException;

/**
 * Checks an identifier and password through the guard's user provider and
 * Laravel's hasher (never by comparing anything itself):
 *
 *  - unknown identifiers still cost one hash check, so response time does
 *    not tell whether an account exists, and both failures get the same message;
 *  - the layered LoginThrottle is checked before and hit after a failure;
 *  - Laravel's Attempting / Failed / Validated events fire as with Auth::attempt();
 *  - a hash made with outdated parameters is upgraded (rehashPasswordIfRequired).
 */
final class CredentialAuthenticator
{
    private static ?string $decoy = null;

    public function __construct(
        private readonly SentinelManager $sentinel,
        private readonly SentinelConfig $config,
        private readonly LoginThrottle $throttle,
        private readonly Hasher $hasher,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @throws ValidationException 422 for wrong credentials, 429 when throttled
     */
    public function authenticate(string $identifier, #[\SensitiveParameter] string $password, bool $remember, ?string $ip): Authenticatable
    {
        $identifier = Identifier::normalize($identifier, $this->config->lowercaseIdentifier);
        $guard = $this->sentinel->guardName();
        $field = $this->config->identifier;

        if (($seconds = $this->throttle->availableIn($identifier, $ip)) !== null) {
            $this->events->dispatch(new LoginFailed(null, $guard, 'throttled'));

            throw ValidationException::withMessages([$field => [__('gcego-sentinel::messages.lockout', ['seconds' => $seconds, 'minutes' => (int) ceil($seconds / 60)])]])->status(429);
        }

        $credentials = [$field => $identifier, 'password' => $password];
        $this->events->dispatch(new Attempting($guard, $credentials, $remember));
        $provider = $this->sentinel->provider();
        $user = $provider->retrieveByCredentials([$field => $identifier]);

        if ($user === null) {
            $this->hasher->check($password, self::decoy($this->hasher));
        }

        if ($user === null || ! $provider->validateCredentials($user, ['password' => $password])) {
            $this->throttle->hit($identifier, $ip);
            $this->events->dispatch(new Failed($guard, $user, $credentials));
            $this->events->dispatch(new LoginFailed($user === null ? null : UserKey::of($user), $guard, 'credentials'));

            throw ValidationException::withMessages([$field => [__('gcego-sentinel::messages.login.failed')]]);
        }

        $provider->rehashPasswordIfRequired($user, ['password' => $password]);
        $this->throttle->clear($identifier, $ip);
        $this->events->dispatch(new Validated($guard, $user));

        return $user;
    }

    /**
     * A hash of a random value with the current parameters, made once per process.
     */
    private static function decoy(Hasher $hasher): string
    {
        return self::$decoy ??= $hasher->make(bin2hex(random_bytes(16)));
    }
}
