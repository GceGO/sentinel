<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Tests\Fixtures\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $role
 * @property string $status
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 */
final class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    use Notifiable;
    use PasskeyAuthenticatable;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
