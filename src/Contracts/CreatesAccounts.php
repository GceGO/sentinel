<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Contracts;

use Gcego\Sentinel\Panels\AuthenticationPanel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

/**
 * Validates registration input and creates the account (in a transaction
 * Sentinel opens). The input holds the built-in fields (name, email,
 * password, password_confirmation) and the configured extra fields only.
 *
 * Implementations must hash the password through Laravel's hasher (or the
 * model's "hashed" cast), must never grant roles or permissions, and should
 * reuse Sentinel's PasswordPolicy::rules() for the password.
 */
interface CreatesAccounts
{
    /**
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input, ?AuthenticationPanel $panel = null): Authenticatable;
}
