<?php

namespace Database\Factories;

use App\Enums\EmploymentType;
use App\Models\Department;
use App\Models\StaffProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StaffProfile>
 */
class StaffProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'employee_number' => 'EMP-'.Str::upper(Str::random(5)),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'job_title' => fake()->jobTitle(),
            'employment_type' => EmploymentType::FullTime,
            'hire_date' => now()->subMonths(fake()->numberBetween(1, 60))->toDateString(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->e164PhoneNumber(),
            'is_active' => true,
        ];
    }
}
