<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'menu_category_id' => MenuCategory::factory(),
            'name' => fake()->randomElement(['Jollof rice with grilled chicken', 'Pepper soup', 'Grilled snapper', 'Club sandwich', 'Fresh mango juice']).' '.fake()->unique()->numberBetween(1, 9999),
            'description' => fake()->sentence(),
            'currency' => Currency::USD,
            'price_minor' => fake()->numberBetween(5, 40) * 100,
            'is_available' => true,
        ];
    }
}
