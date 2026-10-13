<?php

namespace Database\Factories;

use App\Models\KpiTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KpiTemplate>
 */
class KpiTemplateFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'team_id' => null,
            'name' => fake()->unique()->words(2, true),
            'is_active' => true,
        ];
    }
}
