<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Support;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A user's identifier as a scalar for events and logs.
 */
final class UserKey
{
    public static function of(Authenticatable $user): string|int
    {
        $key = $user->getAuthIdentifier();

        return is_int($key) || is_string($key) ? $key : (is_scalar($key) ? (string) $key : '');
    }
}
