<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the account owner about a security change ($kind: "password_changed",
 * "two_factor_enabled", "recovery_code_used", "passkey_registered",
 * "email_changed", "security_hold_applied", "new_sign_in"...), so an
 * unexpected change is noticed. Queued after the database commit.
 *
 * It never contains a password, code, token, secret or a link that acts on
 * the account: only what happened, when, and what to do if it was not them.
 */
class SecurityAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $channels
     * @param  array<string, string|int>  $details  non-secret values for the message (":remaining", ":browser")
     */
    public function __construct(public string $kind, public array $channels = ['mail'], public array $details = [])
    {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $prefix = 'gcego-sentinel::messages.notifications.'.$this->kind;
        $subject = __($prefix.'.subject');
        $line = __($prefix.'.line', $this->details);
        $help = __('gcego-sentinel::messages.notifications.help');

        return (new MailMessage)
            ->subject(is_string($subject) ? $subject : 'Account security')
            ->line(is_string($line) ? $line : '')
            ->line(is_string($help) ? $help : '');
    }

    /**
     * For the database channel (gcego/notifications inbox).
     *
     * @return array{kind: string, details: array<string, string|int>}
     */
    public function toArray(object $notifiable): array
    {
        return ['kind' => $this->kind, 'details' => $this->details];
    }
}
