<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Enums\ServiceCategory;
use App\Models\AdditionalService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdditionalService>
 */
class AdditionalServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Airport transfer', 'Swedish massage', 'Express laundry', 'Private dinner']).' '.fake()->unique()->numberBetween(1, 9999),
            'category' => ServiceCategory::Spa,
            'short_description' => fake()->sentence(),
            'currency' => Currency::USD,
            'price_minor' => 6000,
            'unit' => 'per session',
            'is_active' => true,
        ];
    }
}
