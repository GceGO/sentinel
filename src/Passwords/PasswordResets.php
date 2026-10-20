<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Passwords;

use Gcego\Sentinel\Events\PasswordResetCompleted;
use Gcego\Sentinel\Notifications\SecurityNotifier;
use Gcego\Sentinel\SentinelConfig;
use Gcego\Sentinel\Sessions\SessionRepository;
use Gcego\Sentinel\Sessions\SessionSecurity;
use Gcego\Sentinel\Support\UserKey;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Passwords\PasswordBrokerManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Password;

/**
 * Password recovery through Laravel's password broker: tokens are random,
 * stored hashed, expire (auth.passwords.*.expire), are throttled per
 * address (auth.passwords.*.throttle) and are deleted when used, so each
 * works once. A reset ends every session of the account and cycles its
 * remember-me token; it never signs anyone in.
 */
final readonly class PasswordResets
{
    public function __construct(
        private PasswordBrokerManager $brokers,
        private SentinelConfig $config,
        private PasswordChanger $passwords,
        private SessionRepository $sessions,
        private SessionSecurity $sessionSecurity,
        private Dispatcher $events,
        private SecurityNotifier $notifier,
    ) {}

    /**
     * Send a reset link; the broker status (callers answer the same way for every status).
     */
    public function sendLink(string $email): string
    {
        return $this->broker()->sendResetLink(['email' => $email]);
    }

    /**
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $credentials
     */
    public function reset(array $credentials): string
    {
        $status = $this->broker()->reset($credentials, function (CanResetPassword $user, string $password): void {
            if (! $user instanceof Authenticatable) {
                return;
            }

            $this->passwords->store($user, $password);
            $ended = $this->sessions->deleteAll($user);
            $this->sessionSecurity->revokeAll($user);
            $this->events->dispatch(new PasswordReset($user));
            $this->events->dispatch(new PasswordResetCompleted(UserKey::of($user), $ended));
            $this->notifier->send($user, 'password_changed');
        });

        return is_string($status) ? $status : Password::INVALID_TOKEN;
    }

    public function broker(): PasswordBroker
    {
        return $this->brokers->broker($this->config->passwordBroker);
    }

    /**
     * Statuses that must look like success to the visitor (account enumeration).
     *
     * @return list<string>
     */
    public static function silentStatuses(): array
    {
        return [Password::RESET_LINK_SENT, Password::INVALID_USER, Password::RESET_THROTTLED];
    }
}
