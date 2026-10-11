<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeRate>
 */
class ExchangeRateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'base_currency' => Currency::USD,
            'quote_currency' => Currency::LRD,
            'rate' => (string) fake()->randomFloat(4, 180, 200),
            'effective_at' => now()->subDay(),
            'created_by' => null,
            'notes' => null,
        ];
    }

    public function rate(string $rate): static
    {
        return $this->state(fn (array $attributes) => ['rate' => $rate]);
    }
}
