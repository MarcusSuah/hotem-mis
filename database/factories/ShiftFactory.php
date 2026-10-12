<?php

namespace Database\Factories;

use App\Models\Shift;
use App\Models\StaffProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'staff_profile_id' => StaffProfile::factory(),
            'shift_date' => now()->toDateString(),
            'starts_at' => '07:00',
            'ends_at' => '15:00',
        ];
    }
}
