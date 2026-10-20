<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Passwords;

use Gcego\Sentinel\Events\PasswordChanged;
use Gcego\Sentinel\Exceptions\AuthenticationConfigurationException;
use Gcego\Sentinel\Notifications\SecurityNotifier;
use Gcego\Sentinel\SentinelManager;
use Gcego\Sentinel\Sessions\SessionRepository;
use Gcego\Sentinel\Sessions\SessionSecurity;
use Gcego\Sentinel\Support\UserKey;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Changes a signed-in user's password: the current password must be right
 * and the new one must pass the PasswordPolicy. Then every other session
 * ends (stored sessions are deleted, remember-me tokens are cycled and the
 * session fingerprint changes), the current session stays signed in, and
 * the owner is told.
 */
final readonly class PasswordChanger
{
    public function __construct(
        private PasswordPolicy $policy,
        private Hasher $hasher,
        private SentinelManager $sentinel,
        private SessionRepository $sessions,
        private SessionSecurity $sessionSecurity,
        private Dispatcher $events,
        private SecurityNotifier $notifier,
    ) {}

    /**
     * @param  array{current_password?: string, password?: string, password_confirmation?: string}  $input
     *
     * @throws ValidationException
     */
    public function change(Authenticatable $user, array $input, Session $session): void
    {
        Validator::make($input, [
            'current_password' => PasswordPolicy::currentRules(),
            'password' => [...$this->policy->rules(), 'different:current_password'],
        ])->after(function (\Illuminate\Validation\Validator $validator) use ($user, $input): void {
            if (! $this->sentinel->provider()->validateCredentials($user, ['password' => (string) ($input['current_password'] ?? '')])) {
                $validator->errors()->add('current_password', __('gcego-sentinel::messages.password_update.wrong_current'));
            }
        })->validate();

        $this->store($user, (string) ($input['password'] ?? ''));
        $this->sessions->deleteOthers($user, $session->getId());
        $this->sessionSecurity->revokeAll($user);
        $this->sessionSecurity->acknowledge($session, $user);
        $this->sessionSecurity->rebind($session, $user);
        $this->events->dispatch(new PasswordChanged(UserKey::of($user), $this->sentinel->guardName()));
        $this->notifier->send($user, 'password_changed');
    }

    /**
     * Hash and save a new password and cycle the remember-me token.
     */
    public function store(Authenticatable $user, #[\SensitiveParameter] string $password): void
    {
        if (! $user instanceof Model) {
            throw AuthenticationConfigurationException::invalid('auth.providers.users.model', 'Sentinel stores passwords on an Eloquent user model.');
        }

        $user->forceFill([$user->getAuthPasswordName() => $this->hasher->make($password)]);

        if ($user->getRememberTokenName() !== '') {
            $user->setRememberToken(Str::random(60));
        }

        $user->save();
    }
}
