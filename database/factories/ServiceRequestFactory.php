<?php

namespace Database\Factories;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Models\Department;
use App\Models\RequestCategory;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds rows directly, bypassing the CreateRequest action and its activity log.
 * Use it to arrange a starting state; use the actions to test behaviour.
 *
 * @extends Factory<ServiceRequest>
 */
class ServiceRequestFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            // High range so factory numbers never collide with generated ones in a test.
            'request_number' => 'ATH-'.str_pad((string) fake()->unique()->numberBetween(900000, 999999), 6, '0', STR_PAD_LEFT),
            'requester_id' => User::factory(),
            'department_id' => Department::factory(),
            'category_id' => RequestCategory::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'priority' => RequestPriority::Normal,
            'deadline' => now()->addDays(7),
            'status' => RequestStatus::New,
        ];
    }

    public function status(RequestStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
