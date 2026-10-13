<?php

namespace Database\Factories;

use App\Enums\KpiStatus;
use App\Models\Kpi;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kpi>
 */
class KpiFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            // Sheets are months, so the period is always the first of one.
            'period' => now()->startOfMonth(),
            'due_date' => now()->startOfMonth()->addMonth(),
            'status' => KpiStatus::Pending,
        ];
    }

    public function forPeriod(string $period): static
    {
        return $this->state(fn () => [
            'period' => $period.'-01',
            'due_date' => date('Y-m-d', strtotime($period.'-01 +1 month')),
        ]);
    }

    public function submitted(): static
    {
        return $this->state(fn () => ['status' => KpiStatus::Submitted, 'submitted_at' => now()]);
    }

    public function reviewed(): static
    {
        return $this->state(fn () => [
            'status' => KpiStatus::Reviewed,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now(),
        ]);
    }
}
