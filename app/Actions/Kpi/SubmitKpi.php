<?php

namespace App\Actions\Kpi;

use App\Enums\KpiStatus;
use App\Models\Kpi;
use App\Models\User;
use App\Services\KpiScoring;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Hands a finished sheet up for review.
 *
 * Every KPI must be reported first. A half-filled sheet submitted early would be signed off on a
 * score that only counted part of the work, and the missing lines could never be added because
 * submitting freezes them.
 */
final class SubmitKpi
{
    public function __construct(private readonly KpiScoring $scoring) {}

    public function handle(User $actor, Kpi $kpi): Kpi
    {
        Gate::forUser($actor)->authorize('submit', $kpi);

        return DB::transaction(function () use ($kpi) {
            $kpi = Kpi::query()->whereKey($kpi->id)->lockForUpdate()->firstOrFail();
            $items = $kpi->items()->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['status' => __('errors.kpi_empty')]);
            }

            if ($items->contains(fn ($item) => $item->achievement === null)) {
                throw ValidationException::withMessages(['status' => __('errors.kpi_incomplete')]);
            }

            $kpi->setRelation('items', $items);
            $this->scoring->recalculate($kpi);

            $kpi->status = KpiStatus::Submitted;
            $kpi->submitted_at = now();
            // A resubmission starts a clean review; the previous verdict no longer applies.
            $kpi->reviewed_at = null;
            $kpi->reviewed_by = null;
            $kpi->save();

            return $kpi->load('items', 'user');
        });
    }
}
