<?php

namespace Database\Factories;

use App\Enums\CancellationPenalty;
use App\Models\CancellationPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CancellationPolicy>
 */
class CancellationPolicyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Flexible',
            'description' => 'Free cancellation up to 48 hours before arrival.',
            'free_cancellation_hours' => 48,
            'penalty_type' => CancellationPenalty::FirstNight,
            'penalty_percent' => '0',
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => ['is_default' => true]);
    }

    public function nonRefundable(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Non-refundable',
            'description' => 'The full stay is charged on cancellation.',
            'free_cancellation_hours' => 0,
            'penalty_type' => CancellationPenalty::FullStay,
        ]);
    }

    public function percentage(string $percent, int $freeHours = 48): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => "{$percent}% penalty",
            'free_cancellation_hours' => $freeHours,
            'penalty_type' => CancellationPenalty::Percentage,
            'penalty_percent' => $percent,
        ]);
    }
}
