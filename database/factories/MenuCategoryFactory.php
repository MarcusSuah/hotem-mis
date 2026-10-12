<?php

namespace Database\Factories;

use App\Models\MenuCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuCategory>
 */
class MenuCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Breakfast', 'Starters', 'Mains', 'Desserts', 'Drinks', 'Cocktails']).' '.fake()->unique()->numberBetween(1, 999),
            'display_order' => 0,
            'is_active' => true,
        ];
    }
}
