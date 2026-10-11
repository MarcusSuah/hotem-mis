<?php

namespace Database\Factories;

use App\Models\CancellationPolicy;
use App\Models\RatePlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RatePlan>
 */
class RatePlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Best Available Rate',
            'code' => 'BAR-'.Str::upper(Str::random(5)),
            'description' => 'Our flexible standard rate.',
            'cancellation_policy_id' => CancellationPolicy::factory(),
            'is_refundable' => true,
            'adjustment_percent' => '0',
            'includes_breakfast' => false,
            'min_nights' => 1,
            'deposit_percent' => null,
            'is_public' => true,
            'is_active' => true,
            'is_default' => false,
            'display_order' => 0,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => ['is_default' => true]);
    }

    public function adjustment(string $percent): static
    {
        return $this->state(fn (array $attributes) => ['adjustment_percent' => $percent]);
    }

    public function nonRefundable(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Advance Saver',
            'is_refundable' => false,
            'adjustment_percent' => '-10',
            'cancellation_policy_id' => CancellationPolicy::factory()->nonRefundable(),
        ]);
    }
}
