<?php

namespace App\Actions\Kpi;

use App\Models\Kpi;
use App\Models\User;
use App\Services\KpiScoring;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The employee reports what they actually achieved.
 *
 * Only the numbers move: names, targets and weights belong to the administrator, so a person can
 * never improve their score by rewriting what they were asked to do. Scores are recomputed here
 * rather than stored by hand, so a corrected figure can never leave a stale score behind it.
 */
final class UpdateKpiAchievements
{
    public function __construct(private readonly KpiScoring $scoring) {}

    /**
     * @param  array<int, int|float|string|null>  $achievements  keyed by KPI item id
     */
    public function handle(User $actor, Kpi $kpi, array $achievements): Kpi
    {
        Gate::forUser($actor)->authorize('fill', $kpi);

        return DB::transaction(function () use ($kpi, $achievements) {
            $kpi = Kpi::query()->whereKey($kpi->id)->lockForUpdate()->firstOrFail();
            $items = $kpi->items()->get()->keyBy('id');

            foreach ($achievements as $id => $value) {
                $item = $items->get((int) $id);

                // An id that is not on this sheet is someone else's line; refuse the whole write.
                if ($item === null) {
                    throw ValidationException::withMessages([
                        'achievements' => __('errors.kpi_item_not_on_sheet'),
                    ]);
                }

                $item->achievement = ($value === null || $value === '') ? null : $value;
                $item->save();
            }

            $kpi->unsetRelation('items');

            return $this->scoring->recalculate($kpi)->load('items', 'user');
        });
    }
}
