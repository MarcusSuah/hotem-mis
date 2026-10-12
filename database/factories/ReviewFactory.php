<?php

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models\Reservation;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory()->checkedOut(),
            'guest_id' => fn (array $attributes) => Reservation::query()->whereKey($attributes['reservation_id'])->value('guest_id'),
            'rating' => fake()->numberBetween(3, 5),
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
        ];
    }

    public function approved(): static
    {
        return $this->afterCreating(fn (Review $review) => $review->forceFill(['status' => ReviewStatus::Approved, 'moderated_at' => now()])->save());
    }
}
