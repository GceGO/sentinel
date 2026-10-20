<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Notifications;

use Gcego\Sentinel\SentinelConfig;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Sends security notifications when their gcego-sentinel.notifications
 * switch is on, to notifiable users (Notifiable trait). A failure to send
 * never breaks the security operation that triggered it.
 */
final readonly class SecurityNotifier
{
    /**
     * Notification kind => configuration switch.
     */
    public const array Switches = [
        'password_changed' => 'password_changed',
        'two_factor_enabled' => 'two_factor_changed',
        'two_factor_disabled' => 'two_factor_changed',
        'recovery_code_used' => 'recovery_code_used',
        'passkey_registered' => 'passkeys_changed',
        'passkey_removed' => 'passkeys_changed',
        'email_changed' => 'email_changed',
        'security_hold_applied' => 'security_hold',
        'security_hold_released' => 'security_hold',
        'new_sign_in' => 'new_sign_in',
    ];

    public function __construct(private SentinelConfig $config) {}

    /**
     * @param  array<string, string|int>  $details
     */
    public function send(Authenticatable $user, string $kind, array $details = []): void
    {
        if (! $this->config->notifies(self::Switches[$kind] ?? $kind) || ! method_exists($user, 'notify')) {
            return;
        }

        try {
            $user->notify($kind === 'password_changed'
                ? new PasswordChangedNotification($this->config->notificationChannels)
                : new SecurityAlertNotification($kind, $this->config->notificationChannels, $details));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Notify an address the account no longer uses (the previous email after a change).
     */
    public function sendToAddress(string $email, string $kind): void
    {
        if (! $this->config->notifies(self::Switches[$kind] ?? $kind) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        try {
            Notification::route('mail', $email)->notify(new SecurityAlertNotification($kind, ['mail']));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
