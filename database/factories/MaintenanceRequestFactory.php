<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Enums\MaintenanceCategory;
use App\Enums\MaintenanceStatus;
use App\Enums\TaskPriority;
use App\Models\MaintenanceRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MaintenanceRequest>
 */
class MaintenanceRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'MNT-'.Str::upper(Str::random(6)),
            'category' => fake()->randomElement(MaintenanceCategory::cases()),
            'priority' => TaskPriority::Normal,
            'status' => MaintenanceStatus::Open,
            'title' => fake()->randomElement(['Air conditioner not cooling', 'Leaking tap', 'Light fitting flickering', 'Wardrobe door loose', 'TV remote not working']),
            'description' => fake()->sentence(),
            'location' => 'Main building',
            'currency' => Currency::USD,
        ];
    }
}
