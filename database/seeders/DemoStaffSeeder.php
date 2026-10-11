<?php

namespace Database\Seeders;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Development-only accounts: one per role, plus a protected Super Admin.
 * The shared password comes from DEMO_USER_PASSWORD and defaults to
 * "password" outside production. Never run in production.
 */
class DemoStaffSeeder extends Seeder
{
    public function run(): void
    {
        $password = (string) config('hms.demo_password');

        foreach (SystemRole::cases() as $role) {
            $email = Str::slug($role->value).'@hms.test';

            $user = User::query()->firstOrNew(['email' => $email]);

            if (! $user->exists) {
                $user->fill(['name' => $role->value.' Demo', 'password' => $password]);
                $user->email_verified_at = now();
                $user->is_protected = $role === SystemRole::SuperAdmin
                    && ! User::query()->where('is_protected', true)->exists();
                $user->save();
            }

            $user->syncRoles([$role->value]);
        }

        $this->command->info('Demo accounts: <role-slug>@hms.test (e.g. super-admin@hms.test).');
    }
}
