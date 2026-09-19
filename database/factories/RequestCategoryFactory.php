<?php

namespace Database\Factories;

use App\Models\RequestCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestCategory>
 */
class RequestCategoryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug' => str($name)->slug()->toString(),
            'name_id' => $name,
            'name_en' => $name,
            'guidance_id' => null,
            'guidance_en' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
