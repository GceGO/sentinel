<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Tests\Fixtures\Actions;

use Gcego\Sentinel\Contracts\CreatesAccounts;
use Gcego\Sentinel\Panels\AuthenticationPanel;
use Gcego\Sentinel\Passwords\PasswordPolicy;
use Gcego\Sentinel\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * An application's own account creator (it may store extra registration fields).
 */
class CreateNewUser implements CreatesAccounts
{
    /**
     * What the last registration passed in, without passwords (for tests).
     *
     * @var array<string, mixed>
     */
    public static array $received = [];

    public function create(array $input, ?AuthenticationPanel $panel = null): User
    {
        self::$received = array_diff_key($input, ['password' => true, 'password_confirmation' => true]);

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => app(PasswordPolicy::class)->rules(),
        ])->validate();

        return User::query()->create([
            'name' => $input['name'],
            'email' => mb_strtolower($input['email']),
            'password' => Hash::make($input['password']),
        ]);
    }
}
