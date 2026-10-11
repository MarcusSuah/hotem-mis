<?php

namespace Database\Factories;

use App\Models\Guest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guest>
 */
class GuestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->e164PhoneNumber(),
            'country_code' => fake()->randomElement(['LR', 'US', 'GH', 'NG', 'SL', 'GB', 'CI']),
            'is_vip' => false,
            'marketing_consent' => fake()->boolean(40),
        ];
    }

    public function vip(): static
    {
        return $this->state(fn (array $attributes) => ['is_vip' => true]);
    }

    /**
     * Link the profile to a login account with matching contact details.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
    }
}
