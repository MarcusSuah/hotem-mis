<?php

namespace Database\Factories;

use App\Models\HotelEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HotelEvent>
 */
class HotelEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->words(3, true),
            'summary' => fake()->sentence(),
            'venue' => 'Terrace',
            'starts_at' => now()->addWeek()->setTime(19, 0),
            'ends_at' => now()->addWeek()->setTime(22, 0),
            'is_published' => true,
        ];
    }
}
