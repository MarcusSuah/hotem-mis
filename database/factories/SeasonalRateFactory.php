<?php

namespace Database\Factories;

use App\Models\RoomType;
use App\Models\SeasonalRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeasonalRate>
 */
class SeasonalRateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_type_id' => RoomType::factory(),
            'name' => 'Festive season',
            'start_date' => now()->startOfYear()->setMonth(12)->setDay(15)->toDateString(),
            'end_date' => now()->startOfYear()->setMonth(12)->setDay(31)->toDateString(),
            'rate_minor' => 22000,
            'weekend_rate_minor' => null,
            'priority' => 0,
            'is_active' => true,
        ];
    }
}
