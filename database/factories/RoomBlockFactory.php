<?php

namespace Database\Factories;

use App\Enums\RoomBlockType;
use App\Models\Room;
use App\Models\RoomBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomBlock>
 */
class RoomBlockFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'type' => RoomBlockType::Maintenance,
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
            'reason' => 'Air-conditioning repair',
        ];
    }

    public function between(string $start, string $end): static
    {
        return $this->state(fn (array $attributes) => ['start_date' => $start, 'end_date' => $end]);
    }

    public function released(): static
    {
        return $this->state(fn (array $attributes) => ['released_at' => now()]);
    }
}
