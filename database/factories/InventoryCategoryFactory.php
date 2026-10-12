<?php

namespace Database\Factories;

use App\Models\InventoryCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryCategory>
 */
class InventoryCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Guest amenities', 'Cleaning supplies', 'Linen', 'Food & beverage', 'Maintenance spares', 'Stationery']).' '.fake()->unique()->numberBetween(1, 999),
        ];
    }
}
