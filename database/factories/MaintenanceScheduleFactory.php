<?php

namespace Database\Factories;

use App\Enums\MaintenanceCategory;
use App\Enums\TaskPriority;
use App\Models\MaintenanceSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceSchedule>
 */
class MaintenanceScheduleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Service air-conditioning units',
            'category' => MaintenanceCategory::Hvac,
            'priority' => TaskPriority::Normal,
            'frequency_days' => 90,
            'next_due_on' => now()->addDays(10)->toDateString(),
            'location' => 'All guest floors',
            'is_active' => true,
        ];
    }
}
