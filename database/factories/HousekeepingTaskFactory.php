<?php

namespace Database\Factories;

use App\Enums\HousekeepingTaskStatus;
use App\Enums\HousekeepingTaskType;
use App\Enums\TaskPriority;
use App\Models\HousekeepingTask;
use App\Models\Room;
use App\Services\Operations\HousekeepingService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HousekeepingTask>
 */
class HousekeepingTaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'type' => HousekeepingTaskType::DepartureClean,
            'priority' => TaskPriority::Normal,
            'status' => HousekeepingTaskStatus::Pending,
            'scheduled_for' => now()->toDateString(),
            'checklist' => HousekeepingService::checklistFor(HousekeepingTaskType::DepartureClean),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => HousekeepingTaskStatus::Completed,
            'checklist' => array_map(fn (array $line) => [...$line, 'done' => true], HousekeepingService::checklistFor(HousekeepingTaskType::DepartureClean)),
        ]);
    }
}
