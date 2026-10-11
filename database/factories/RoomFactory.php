<?php

namespace Database\Factories;

use App\Enums\HousekeepingStatus;
use App\Enums\OperationalStatus;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(101, 9999);

        return [
            'room_type_id' => RoomType::factory(),
            'property_id' => fn (array $attributes) => RoomType::query()->whereKey($attributes['room_type_id'])->value('property_id'),
            'number' => (string) $number,
            'floor' => intdiv($number, 100),
            'building' => 'Main',
            'operational_status' => OperationalStatus::InService,
            'housekeeping_status' => HousekeepingStatus::Inspected,
            'is_accessible' => false,
        ];
    }

    public function outOfService(): static
    {
        return $this->state(fn (array $attributes) => ['operational_status' => OperationalStatus::OutOfService]);
    }

    public function dirty(): static
    {
        return $this->state(fn (array $attributes) => ['housekeeping_status' => HousekeepingStatus::Dirty]);
    }
}
