<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Support;

/**
 * The text of a session "status": Sentinel's status keys
 * ("verification-link-sent", "two-factor-authentication-enabled"...) are
 * translated; other values are already messages (the password broker's).
 * Views escape the result.
 */
final class StatusMessages
{
    public const array Keys = [
        'verification-link-sent',
        'two-factor-authentication-enabled',
        'two-factor-authentication-confirmed',
        'two-factor-authentication-disabled',
        'recovery-codes-generated',
        'password-updated',
        'profile-information-updated',
        'two-factor-required',
    ];

    public static function for(mixed $status): ?string
    {
        if (! is_string($status) || $status === '' || mb_strlen($status) > 500) {
            return null;
        }

        if (in_array($status, self::Keys, true)) {
            $message = __('gcego-sentinel::messages.status.'.$status);

            return is_string($message) ? $message : null;
        }

        return $status;
    }
}
