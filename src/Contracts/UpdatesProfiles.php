<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

/**
 * Validates and saves the signed-in user's profile fields
 * (gcego-sentinel.profile.fields). Sentinel has already confirmed the
 * password when the sign-in identifier changes, and handles re-verification
 * and the "email changed" notice afterwards.
 */
interface UpdatesProfiles
{
    /**
     * @param  array<string, string>  $input  configured field => value
     *
     * @throws ValidationException
     */
    public function update(Authenticatable $user, array $input): void;
}
