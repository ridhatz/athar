<?php

namespace App\Services;

use App\Enums\KpiDirection;
use App\Models\Kpi;
use App\Models\KpiItem;

/**
 * The only place a KPI score is worked out.
 *
 * One KPI is worth its weight times how far the achievement got against the target:
 *
 *   higher is better   score = weight × (achievement ÷ target)
 *   lower is better    score = weight × (target ÷ achievement)
 *
 * so 21 points of weight against a "<35%" target reported as 32% earns 21 × (35÷32) = 22.97,
 * and the same weight against a "<90%" target reported as 108% earns 21 × (90÷108) = 17.50.
 * The sheet's score is those added up — it can exceed 100, because beating targets should show.
 *
 * Nothing here is cached or stored by hand: the score is always recomputed from the numbers, so a
 * corrected achievement can never leave a stale score behind it.
 */
final class KpiScoring
{
    /**
     * What one KPI earns, or null when the employee has not reported anything yet.
     *
     * Null is not zero: an unfilled KPI is unknown, while a reported 0 is a real (bad) result.
     */
    public function score(KpiItem $item): ?float
    {
        if ($item->achievement === null) {
            return null;
        }

        $ratio = $this->ratio((float) $item->target_value, (float) $item->achievement, $item->direction);

        return round((float) $item->weight * $ratio, 2);
    }

    /**
     * How far the achievement got, as a multiple of the target.
     *
     * Capped at `athar.kpi.max_ratio`, which is what keeps a "lower is better" KPI reported as 0
     * from dividing by zero and scoring infinity. A target of 0 is treated the same way: there is
     * no meaningful ratio against it, so a non-zero achievement takes the ceiling and a zero one
     * counts as having met it exactly.
     */
    public function ratio(float $target, float $achievement, KpiDirection $direction): float
    {
        $max = (float) config('athar.kpi.max_ratio', 2.0);

        [$numerator, $denominator] = $direction === KpiDirection::HigherBetter
            ? [$achievement, $target]
            : [$target, $achievement];

        if ($denominator == 0.0) {
            return $numerator == 0.0 ? 1.0 : $max;
        }

        return min($numerator / $denominator, $max);
    }

    /**
     * Rewrite every item's score and the sheet's total from the numbers currently on record.
     *
     * The total only counts KPIs that have been reported, so a half-filled sheet shows what it has
     * earned so far rather than pretending the rest scored zero. A sheet with nothing filled in
     * has no score at all.
     */
    public function recalculate(Kpi $kpi): Kpi
    {
        $items = $kpi->relationLoaded('items') ? $kpi->items : $kpi->items()->get();

        $total = null;

        foreach ($items as $item) {
            $score = $this->score($item);

            if ($item->score !== ($score === null ? null : (string) $score)) {
                $item->score = $score;
                $item->save();
            }

            if ($score !== null) {
                $total = ($total ?? 0.0) + $score;
            }
        }

        $kpi->final_score = $total === null ? null : round($total, 2);
        $kpi->save();

        return $kpi;
    }

    /**
     * How much of the sheet has been reported, 0–100.
     *
     * This is the progress bar on the list: it measures filling in, not performance.
     *
     * @param  iterable<int, KpiItem>  $items
     */
    public function progress(iterable $items): int
    {
        $total = 0;
        $filled = 0;

        foreach ($items as $item) {
            $total++;
            if ($item->achievement !== null) {
                $filled++;
            }
        }

        return $total === 0 ? 0 : (int) round($filled / $total * 100);
    }
}
