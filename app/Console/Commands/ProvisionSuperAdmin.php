<?php

namespace App\Console\Commands;

use App\Enums\SystemRole;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;

#[Signature('hms:provision-super-admin
    {--email= : Email of the protected account (defaults to SUPER_ADMIN_EMAIL)}
    {--name= : Display name (defaults to SUPER_ADMIN_NAME)}
    {--generate-password : Generate a random password instead of prompting}')]
#[Description('Create or repair the protected Super Admin account without storing credentials in source control')]
class ProvisionSuperAdmin extends Command
{
    public function handle(AuditLogger $audit): int
    {
        $email = Str::lower((string) ($this->option('email') ?: config('hms.super_admin.email')));
        $name = (string) ($this->option('name') ?: config('hms.super_admin.name'));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email']])->fails()) {
            $this->components->error('Provide a valid --email or set SUPER_ADMIN_EMAIL.');

            return self::FAILURE;
        }

        $existingProtected = User::withTrashed()->where('is_protected', true)->first();

        if ($existingProtected && $existingProtected->email !== $email) {
            $this->components->error("A protected Super Admin already exists ({$existingProtected->email}). Only one protected account is allowed.");

            return self::FAILURE;
        }

        (new RolesAndPermissionsSeeder)->run();

        $user = User::withTrashed()->where('email', $email)->first();
        $generatedPassword = null;

        if ($user === null) {
            $plainPassword = $this->resolvePassword($generatedPassword);

            if ($plainPassword === null) {
                return self::FAILURE;
            }

            $user = new User(['name' => $name, 'email' => $email, 'password' => $plainPassword]);
            $user->email_verified_at = now();
        }

        DB::transaction(function () use ($user, $audit): void {
            if ($user->trashed()) {
                $user->restore();
            }

            $user->forceFill(['is_protected' => true, 'is_active' => true, 'deactivated_at' => null])->save();
            $user->assignRole(SystemRole::SuperAdmin->value);

            $audit->record('super_admin.provisioned', $user, newValues: ['email' => $user->email]);
        });

        $this->components->info("Protected Super Admin ready: {$user->email}");

        if ($generatedPassword !== null) {
            $this->components->warn('Generated password (shown once, store it securely and change it after first login):');
            $this->line($generatedPassword);
        }

        return self::SUCCESS;
    }

    private function resolvePassword(?string &$generatedPassword): ?string
    {
        if ($this->option('generate-password') || ! $this->input->isInteractive()) {
            return $generatedPassword = Str::password(20);
        }

        $plain = password(label: 'Password for the Super Admin account', required: true);
        $confirmation = password(label: 'Confirm password', required: true);

        $validator = Validator::make(
            ['password' => $plain, 'password_confirmation' => $confirmation],
            ['password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()]],
        );

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first('password'));

            return null;
        }

        return $plain;
    }
}
