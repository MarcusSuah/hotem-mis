<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Front Office', 'Housekeeping', 'Maintenance', 'Food & Beverage', 'Finance', 'Sales & Marketing']).' '.fake()->unique()->numberBetween(1, 999),
        ];
    }
}
