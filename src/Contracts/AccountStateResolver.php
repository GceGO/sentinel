<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Contracts;

use Gcego\Sentinel\Accounts\AccountState;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The current state of an account, read fresh (a suspension takes effect on
 * the next request). Only Active accounts may sign in or keep a session.
 */
interface AccountStateResolver
{
    public function state(Authenticatable $user): AccountState;
}
