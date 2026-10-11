<?php

namespace Database\Seeders;

use App\Enums\Currency;
use App\Models\Property;
use App\Models\SupportedCurrency;
use Illuminate\Database\Seeder;

/**
 * Baseline records every environment needs: the default property and the
 * supported currencies. Safe to rerun.
 */
class FoundationSeeder extends Seeder
{
    public function run(): void
    {
        Property::query()->firstOrCreate(
            ['code' => 'MAIN'],
            ['name' => 'The Midnight Luxe Hotel', 'slug' => 'midnight-luxe-hotel', 'timezone' => 'Africa/Monrovia', 'is_default' => true],
        );

        foreach (Currency::cases() as $currency) {
            SupportedCurrency::query()->updateOrCreate(
                ['code' => $currency->value],
                ['name' => $currency->label(), 'symbol' => $currency->symbol(), 'minor_units' => $currency->minorUnits()],
            );
        }
    }
}
