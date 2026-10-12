<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->words(3, true),
            'summary' => fake()->sentence(),
            'body' => fake()->paragraphs(3, true),
            'is_published' => true,
            'show_in_footer' => false,
            'display_order' => 0,
        ];
    }
}
