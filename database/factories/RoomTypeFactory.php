<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\Property;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomType>
 */
class RoomTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => fn () => Property::query()->where('is_default', true)->value('id') ?? Property::factory()->default(),
            'name' => fake()->unique()->randomElement(['Classic', 'Superior', 'Deluxe', 'Premier', 'Junior', 'Garden', 'Ocean']).' '.fake()->randomElement(['Room', 'King', 'Twin', 'Suite']).' '.fake()->unique()->numberBetween(1, 9999),
            'short_description' => fake()->sentence(12),
            'description' => fake()->paragraphs(2, true),
            'max_occupancy' => 3,
            'max_adults' => 2,
            'max_children' => 1,
            'included_adults' => 2,
            'included_children' => 0,
            'bed_type' => 'King',
            'bed_count' => 1,
            'size_sqm' => '32.0',
            'bathroom_details' => 'Walk-in rain shower',
            'currency' => Currency::USD,
            'base_rate_minor' => 15000,
            'weekend_rate_minor' => null,
            'extra_adult_minor' => 3000,
            'extra_child_minor' => 1500,
            'is_active' => true,
            'is_featured' => false,
            'display_order' => 0,
        ];
    }

    public function rate(int $baseMinor, ?int $weekendMinor = null): static
    {
        return $this->state(fn (array $attributes) => [
            'base_rate_minor' => $baseMinor,
            'weekend_rate_minor' => $weekendMinor,
        ]);
    }

    public function capacity(int $adults, int $children = 0, ?int $total = null): static
    {
        return $this->state(fn (array $attributes) => [
            'max_adults' => $adults,
            'max_children' => $children,
            'max_occupancy' => $total ?? $adults + $children,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => ['is_featured' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
