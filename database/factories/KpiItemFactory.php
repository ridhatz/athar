<?php

namespace Database\Factories;

use App\Enums\KpiDirection;
use App\Enums\KpiFrequency;
use App\Models\Kpi;
use App\Models\KpiItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KpiItem>
 */
class KpiItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'kpi_id' => Kpi::factory(),
            'name' => fake()->sentence(4),
            'target_value' => 100,
            'direction' => KpiDirection::HigherBetter,
            'unit' => null,
            'weight' => 25,
            'frequency' => KpiFrequency::Monthly,
            'achievement' => null,
            'sort_order' => 0,
        ];
    }

    public function lowerBetter(): static
    {
        return $this->state(fn () => ['direction' => KpiDirection::LowerBetter]);
    }
}
