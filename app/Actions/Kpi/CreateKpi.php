<?php

namespace App\Actions\Kpi;

use App\Enums\KpiStatus;
use App\Models\Kpi;
use App\Models\KpiItem;
use App\Models\User;
use App\Services\AdminAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Opens a KPI sheet for one person and one month, with the targets and weights an administrator
 * decided. Achievements are not set here — those are the employee's to report.
 */
final class CreateKpi
{
    public function __construct(private readonly AdminAudit $audit) {}

    /**
     * @param  array{user_id: int, period: string, due_date?: string|null, items: array<int, array{name: string, target_value: float|int|string, direction: string, unit?: string|null, weight: float|int|string, frequency?: string|null}>}  $data
     */
    public function handle(User $actor, array $data): Kpi
    {
        Gate::forUser($actor)->authorize('create', Kpi::class);

        // A month, not a day: two sheets for the same person in September would both be "September".
        $period = date('Y-m-01', strtotime($data['period'].'-01'));

        return DB::transaction(function () use ($actor, $data, $period) {
            $exists = Kpi::query()->where('user_id', $data['user_id'])->whereDate('period', $period)->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'period' => __('errors.kpi_period_taken'),
                ]);
            }

            $kpi = Kpi::query()->create([
                'user_id' => $data['user_id'],
                'period' => $period,
                'due_date' => $data['due_date'] ?? date('Y-m-d', strtotime($period.' +1 month')),
                'status' => KpiStatus::Pending,
                'created_by' => $actor->id,
            ]);

            foreach (array_values($data['items']) as $i => $item) {
                KpiItem::query()->create([
                    'kpi_id' => $kpi->id,
                    'name' => trim($item['name']),
                    'target_value' => $item['target_value'],
                    'direction' => $item['direction'],
                    'unit' => $item['unit'] ?? null,
                    'weight' => $item['weight'],
                    'frequency' => $item['frequency'] ?? 'MONTHLY',
                    'sort_order' => $i,
                ]);
            }

            $this->audit->record($actor, $kpi, 'KPI_CREATED', null, [
                'user_id' => $kpi->user_id,
                'period' => $kpi->periodKey(),
                'items' => count($data['items']),
            ]);

            return $kpi->load('items', 'user');
        });
    }
}
