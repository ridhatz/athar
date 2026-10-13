<?php

use App\Enums\KpiDirection;
use App\Models\Kpi;
use App\Models\KpiItem;
use App\Services\KpiScoring;

beforeEach(function () {
    $this->scoring = app(KpiScoring::class);
});

/**
 * The September 2026 sheet from the HRIS screenshot this module was modelled on, number for
 * number. If the arithmetic ever drifts, this is the test that says so.
 *
 * @return array<int, array{name: string, target: float, direction: KpiDirection, weight: float, achievement: float, score: float}>
 */
function septemberSheet(): array
{
    return [
        ['name' => 'Menurunkan biaya operasional terhadap pendapatan lembaga',
            'target' => 90, 'direction' => KpiDirection::LowerBetter, 'weight' => 21, 'achievement' => 108, 'score' => 17.50],
        ['name' => 'Meningkatkan penerimaan dana donasi',
            'target' => 1_470_000_000, 'direction' => KpiDirection::HigherBetter, 'weight' => 29, 'achievement' => 1_298_357_101, 'score' => 25.61],
        ['name' => 'Meningkatkan penerimaan dana donasi (jumlah donatur)',
            'target' => 55_000, 'direction' => KpiDirection::HigherBetter, 'weight' => 21, 'achievement' => 59_561, 'score' => 22.74],
        ['name' => 'Menurunkan nilai capaian terhadap biaya iklan',
            'target' => 35, 'direction' => KpiDirection::LowerBetter, 'weight' => 21, 'achievement' => 32, 'score' => 22.97],
        ['name' => 'Meningkatkan persentase kampanye mitra yang mencapai target biaya iklan',
            'target' => 2, 'direction' => KpiDirection::HigherBetter, 'weight' => 8, 'achievement' => 2, 'score' => 8.00],
    ];
}

describe('scoring one KPI', function () {
    it('reproduces every line of the September 2026 sheet', function () {
        foreach (septemberSheet() as $row) {
            $item = KpiItem::factory()->make([
                'target_value' => $row['target'],
                'direction' => $row['direction'],
                'weight' => $row['weight'],
                'achievement' => $row['achievement'],
            ]);

            expect($this->scoring->score($item))->toBe($row['score'], $row['name']);
        }
    });

    it('rewards beating a target instead of stopping at the weight', function () {
        $item = KpiItem::factory()->make(['target_value' => 100, 'weight' => 20, 'achievement' => 150]);

        // 20 × 1.5: exceeding the target is worth more than meeting it.
        expect($this->scoring->score($item))->toBe(30.0);
    });

    it('scores a lower-is-better KPI higher the smaller the number', function () {
        $better = KpiItem::factory()->lowerBetter()->make(['target_value' => 100, 'weight' => 20, 'achievement' => 50]);
        $worse = KpiItem::factory()->lowerBetter()->make(['target_value' => 100, 'weight' => 20, 'achievement' => 200]);

        expect($this->scoring->score($better))->toBeGreaterThan($this->scoring->score($worse));
    });

    it('leaves an unreported KPI unscored rather than scoring it zero', function () {
        $item = KpiItem::factory()->make(['achievement' => null]);

        expect($this->scoring->score($item))->toBeNull();
    });

    it('treats a reported zero as a real, bad result', function () {
        $item = KpiItem::factory()->make(['target_value' => 100, 'weight' => 20, 'achievement' => 0]);

        expect($this->scoring->score($item))->toBe(0.0);
    });
});

describe('the ceiling that keeps the arithmetic finite', function () {
    it('does not divide by zero when a lower-is-better KPI comes in at zero', function () {
        $item = KpiItem::factory()->lowerBetter()->make(['target_value' => 100, 'weight' => 20, 'achievement' => 0]);

        // Without the cap this would be 100 ÷ 0.
        expect($this->scoring->score($item))->toBe(40.0);
    });

    it('caps a runaway ratio at the configured maximum', function () {
        config(['athar.kpi.max_ratio' => 2.0]);
        $item = KpiItem::factory()->make(['target_value' => 1, 'weight' => 10, 'achievement' => 10_000]);

        expect($this->scoring->score($item))->toBe(20.0);
    });

    it('honours a different ceiling from config', function () {
        config(['athar.kpi.max_ratio' => 3.0]);
        $item = KpiItem::factory()->make(['target_value' => 1, 'weight' => 10, 'achievement' => 10_000]);

        expect($this->scoring->score($item))->toBe(30.0);
    });

    it('counts a zero target met by a zero achievement as exactly on target', function () {
        $item = KpiItem::factory()->make(['target_value' => 0, 'weight' => 10, 'achievement' => 0]);

        expect($this->scoring->score($item))->toBe(10.0);
    });
});

describe('scoring a whole sheet', function () {
    it('adds the September sheet up to the 96.82 the screenshot shows', function () {
        $kpi = Kpi::factory()->create();

        foreach (septemberSheet() as $i => $row) {
            KpiItem::factory()->for($kpi)->create([
                'name' => $row['name'],
                'target_value' => $row['target'],
                'direction' => $row['direction'],
                'weight' => $row['weight'],
                'achievement' => $row['achievement'],
                'sort_order' => $i,
            ]);
        }

        $this->scoring->recalculate($kpi);

        expect((float) $kpi->fresh()->final_score)->toBe(96.82);
    });

    it('counts only what has been reported, so a half-filled sheet is not punished', function () {
        $kpi = Kpi::factory()->create();
        KpiItem::factory()->for($kpi)->create(['target_value' => 100, 'weight' => 50, 'achievement' => 100]);
        KpiItem::factory()->for($kpi)->create(['target_value' => 100, 'weight' => 50, 'achievement' => null]);

        $this->scoring->recalculate($kpi);

        // 50, not 50-out-of-100: the unreported half is unknown, not failed.
        expect((float) $kpi->fresh()->final_score)->toBe(50.0);
    });

    it('gives a sheet with nothing reported no score at all', function () {
        $kpi = Kpi::factory()->create();
        KpiItem::factory()->count(3)->for($kpi)->create(['achievement' => null]);

        $this->scoring->recalculate($kpi);

        expect($kpi->fresh()->final_score)->toBeNull();
    });

    it('never leaves a stale score behind a corrected achievement', function () {
        $kpi = Kpi::factory()->create();
        $item = KpiItem::factory()->for($kpi)->create(['target_value' => 100, 'weight' => 20, 'achievement' => 100]);
        $this->scoring->recalculate($kpi);
        expect((float) $kpi->fresh()->final_score)->toBe(20.0);

        $item->update(['achievement' => 50]);
        $this->scoring->recalculate($kpi);

        expect((float) $kpi->fresh()->final_score)->toBe(10.0)
            ->and((float) $item->fresh()->score)->toBe(10.0);
    });
});

describe('how full a sheet is', function () {
    it('measures filling in, not performance', function () {
        $kpi = Kpi::factory()->create();
        KpiItem::factory()->count(3)->for($kpi)->create(['achievement' => 1]);
        KpiItem::factory()->for($kpi)->create(['achievement' => null]);

        expect($this->scoring->progress($kpi->items))->toBe(75);
    });

    it('is zero for a sheet with no KPIs on it', function () {
        expect($this->scoring->progress([]))->toBe(0);
    });
});
