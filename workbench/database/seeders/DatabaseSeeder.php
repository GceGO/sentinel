<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Gcego\Sentinel\Tests\Fixtures\Models\Membership;
use Gcego\Sentinel\Tests\Fixtures\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo accounts (password: "password"): admin@, teacher@, student@, member@ (institution 1), suspended@.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin' => 'admin', 'teacher' => 'teacher', 'student' => 'student', 'member' => null, 'suspended' => null] as $name => $role) {
            $user = User::query()->updateOrCreate(['email' => $name.'@gcego.test'], [
                'name' => ucfirst($name).' Demo',
                'email' => $name.'@gcego.test',
                'password' => 'password',
                'role' => $role,
                'status' => $name === 'suspended' ? 'suspended' : 'active',
                'email_verified_at' => now(),
            ]);

            if ($name === 'member') {
                Membership::query()->updateOrCreate(['user_id' => $user->id, 'institution_id' => 1], ['permissions' => ['institution.access']]);
            }
        }
    }
}
