<?php

namespace Database\Factories;

use App\Models\Amenity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Amenity>
 */
class AmenityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'Free Wi-Fi', 'Air conditioning', 'Smart TV', 'Minibar', 'In-room safe', 'Rain shower',
                'Ocean view', 'Work desk', 'Espresso machine', 'Bathrobes', 'Balcony', 'Blackout curtains',
            ]),
            'icon' => 'sparkles',
            'category' => 'room',
            'is_active' => true,
            'display_order' => fake()->numberBetween(0, 20),
        ];
    }
}
