<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Commands\Concerns;

use Gcego\Sentinel\SentinelManager;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Operator commands find the account by its sign-in identifier or its id.
 */
trait FindsUsers
{
    protected function findUser(string $value): ?Authenticatable
    {
        $sentinel = $this->laravel->make(SentinelManager::class);
        $provider = $sentinel->provider();

        if (ctype_digit($value) && ($user = $provider->retrieveById((int) $value)) !== null) {
            return $user;
        }

        return $provider->retrieveByCredentials([$sentinel->config()->identifier => $sentinel->config()->lowercaseIdentifier ? mb_strtolower(trim($value)) : trim($value)]);
    }
}
