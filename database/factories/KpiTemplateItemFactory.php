<?php

namespace Database\Factories;

use App\Enums\KpiDirection;
use App\Enums\KpiFrequency;
use App\Models\KpiTemplate;
use App\Models\KpiTemplateItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KpiTemplateItem>
 */
class KpiTemplateItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'kpi_template_id' => KpiTemplate::factory(),
            'name' => fake()->sentence(4),
            'target_value' => 100,
            'direction' => KpiDirection::HigherBetter,
            'unit' => null,
            'weight' => 25,
            'frequency' => KpiFrequency::Monthly,
            'sort_order' => 0,
        ];
    }
}
