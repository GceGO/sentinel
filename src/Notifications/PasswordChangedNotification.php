<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Notifications;

/**
 * Tells the account owner their password changed (on the account page or
 * through a reset link). It contains no password, token or link that signs anyone in.
 */
final class PasswordChangedNotification extends SecurityAlertNotification
{
    /**
     * @param  list<string>  $channels
     */
    public function __construct(array $channels = ['mail'])
    {
        parent::__construct('password_changed', $channels);
    }
}
