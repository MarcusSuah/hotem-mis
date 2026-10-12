<?php

namespace Database\Factories;

use App\Enums\LostFoundStatus;
use App\Models\LostFoundItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LostFoundItem>
 */
class LostFoundItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'LF-'.Str::upper(Str::random(6)),
            'description' => fake()->randomElement(['Black phone charger', 'Silver bracelet', 'Blue umbrella', 'Reading glasses', 'Paperback novel']),
            'location_found' => 'Bedside table',
            'storage_location' => 'Housekeeping office, shelf B',
            'found_at' => now()->subDays(fake()->numberBetween(0, 30)),
            'status' => LostFoundStatus::Stored,
        ];
    }
}
