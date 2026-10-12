<?php

namespace App\Services\Guests;

use App\Models\Guest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Str;

class GuestService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Reuse an existing profile with the same email address, otherwise create one.
     *
     * @param  array{first_name: string, last_name: string, email: string|null, phone: string|null, country_code?: string|null}  $details
     */
    public function findOrCreate(array $details): Guest
    {
        $email = filled($details['email']) ? Str::lower((string) $details['email']) : null;

        if ($email !== null) {
            $existing = Guest::query()->where('email', $email)->orderBy('id')->first();

            if ($existing !== null) {
                if (blank($existing->phone) && filled($details['phone'])) {
                    $existing->update(['phone' => $details['phone']]);
                }

                return $existing;
            }
        }

        $guest = Guest::create([...$details, 'email' => $email]);

        $this->audit->record('guest.created', $guest, newValues: ['name' => $guest->full_name]);

        return $guest;
    }

    /**
     * The guest profile for a signed-in user, created from the account on first use.
     */
    public function forUser(User $user): Guest
    {
        if ($user->loadMissing('guest')->guest !== null) {
            return $user->guest;
        }

        [$first, $last] = array_pad(explode(' ', trim($user->name), 2), 2, '');

        $existing = Guest::query()->whereNull('user_id')->where('email', Str::lower($user->email))->first();

        if ($existing !== null) {
            $existing->update(['user_id' => $user->id]);

            return $existing;
        }

        return Guest::create([
            'user_id' => $user->id,
            'first_name' => $first,
            'last_name' => $last !== '' ? $last : '-',
            'email' => Str::lower($user->email),
            'phone' => $user->phone,
            'preferred_currency' => $user->preferred_currency,
        ]);
    }
}
