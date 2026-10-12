<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventory_category_id' => InventoryCategory::factory(),
            'sku' => 'SKU-'.Str::upper(Str::random(6)),
            'name' => fake()->randomElement(['Shampoo 30ml', 'Bath towel', 'Toilet roll', 'Coffee pods', 'LED bulb', 'Bed sheet (king)']),
            'unit' => 'pcs',
            'reorder_level' => '10',
            'currency' => Currency::USD,
            'tracks_expiry' => false,
            'is_active' => true,
        ];
    }
}
