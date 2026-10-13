<?php

use App\Actions\Kpi\CreateKpi;
use App\Actions\Kpi\ReviewKpi;
use App\Actions\Kpi\SubmitKpi;
use App\Actions\Kpi\UpdateKpiAchievements;
use App\Enums\KpiDirection;
use App\Enums\KpiStatus;
use App\Models\AuditLog;
use App\Models\Kpi;
use App\Models\KpiItem;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->c = cast();
});

/** A sheet for Ahmad (Creative) with two KPIs, opened by the admin. */
function sheetFor(object $c, ?string $period = null): Kpi
{
    return app(CreateKpi::class)->handle($c->admin, [
        'user_id' => $c->ahmad->id,
        'period' => $period ?? '2026-09',
        'items' => [
            ['name' => 'Meningkatkan penerimaan dana donasi', 'target_value' => 55000,
                'direction' => KpiDirection::HigherBetter->value, 'weight' => 60],
            ['name' => 'Menurunkan biaya operasional', 'target_value' => 90, 'unit' => '%',
                'direction' => KpiDirection::LowerBetter->value, 'weight' => 40],
        ],
    ]);
}

describe('opening a sheet', function () {
    it('is the administrator\'s job, not the employee\'s', function () {
        expect(fn () => app(CreateKpi::class)->handle($this->c->ahmad, [
            'user_id' => $this->c->ahmad->id, 'period' => '2026-09',
            'items' => [['name' => 'X', 'target_value' => 1, 'direction' => 'HIGHER_BETTER', 'weight' => 100]],
        ]))->toThrow(AuthorizationException::class);

        expect(Kpi::query()->count())->toBe(0);
    });

    it('starts pending, unscored, and records who opened it', function () {
        $kpi = sheetFor($this->c);

        expect($kpi->status)->toBe(KpiStatus::Pending)
            ->and($kpi->final_score)->toBeNull()
            ->and($kpi->items)->toHaveCount(2)
            ->and($kpi->created_by)->toBe($this->c->admin->id);

        expect(AuditLog::query()->where('action', 'KPI_CREATED')->exists())->toBeTrue();
    });

    it('pins the period to the first of the month', function () {
        expect(sheetFor($this->c, '2026-09')->period->format('Y-m-d'))->toBe('2026-09-01');
    });

    it('refuses a second sheet for the same person and month', function () {
        sheetFor($this->c);

        expect(fn () => sheetFor($this->c))->toThrow(ValidationException::class);
        expect(Kpi::query()->count())->toBe(1);
    });
});

describe('reporting achievements', function () {
    it('lets the owner fill in the numbers and scores them at once', function () {
        $kpi = sheetFor($this->c);
        [$donasi, $biaya] = $kpi->items->all();

        $kpi = app(UpdateKpiAchievements::class)->handle($this->c->ahmad, $kpi, [
            $donasi->id => 59561,
            $biaya->id => 108,
        ]);

        // 60 × (59561÷55000) = 64.98, and 40 × (90÷108) = 33.33
        expect((float) $kpi->fresh()->final_score)->toBe(98.31);
    });

    it('refuses numbers from anyone but the owner', function () {
        $kpi = sheetFor($this->c);

        foreach ([$this->c->budi, $this->c->creativeLead, $this->c->admin] as $outsider) {
            expect(fn () => app(UpdateKpiAchievements::class)->handle($outsider, $kpi, [$kpi->items->first()->id => 1]))
                ->toThrow(AuthorizationException::class);
        }
    });

    it('refuses a KPI id that belongs to somebody else\'s sheet', function () {
        $kpi = sheetFor($this->c);
        $other = KpiItem::factory()->create();

        expect(fn () => app(UpdateKpiAchievements::class)->handle($this->c->ahmad, $kpi, [$other->id => 99]))
            ->toThrow(ValidationException::class);

        expect($other->fresh()->achievement)->toBeNull();
    });

    it('lets a wrongly reported number be corrected, with no stale score left behind', function () {
        $kpi = sheetFor($this->c);
        $donasi = $kpi->items->first();

        app(UpdateKpiAchievements::class)->handle($this->c->ahmad, $kpi, [$donasi->id => 55000]);
        expect((float) $donasi->fresh()->score)->toBe(60.0);

        app(UpdateKpiAchievements::class)->handle($this->c->ahmad, $kpi, [$donasi->id => 27500]);
        expect((float) $donasi->fresh()->score)->toBe(30.0);
    });
});

describe('submitting', function () {
    it('will not go up half-filled', function () {
        $kpi = sheetFor($this->c);
        app(UpdateKpiAchievements::class)->handle($this->c->ahmad, $kpi, [$kpi->items->first()->id => 55000]);

        expect(fn () => app(SubmitKpi::class)->handle($this->c->ahmad, $kpi->fresh()))
            ->toThrow(ValidationException::class);

        expect($kpi->fresh()->status)->toBe(KpiStatus::Pending);
    });

    it('goes up once every KPI has been reported', function () {
        $kpi = reportedSheet($this->c);

        $kpi = app(SubmitKpi::class)->handle($this->c->ahmad, $kpi);

        expect($kpi->status)->toBe(KpiStatus::Submitted)
            ->and($kpi->submitted_at)->not->toBeNull();
    });

    it('freezes the numbers once submitted', function () {
        $kpi = app(SubmitKpi::class)->handle($this->c->ahmad, reportedSheet($this->c));

        expect(fn () => app(UpdateKpiAchievements::class)->handle($this->c->ahmad, $kpi, [$kpi->items->first()->id => 1]))
            ->toThrow(AuthorizationException::class);
    });
});

describe('review', function () {
    it('is for a supervisor, never for the person being scored', function () {
        $kpi = app(SubmitKpi::class)->handle($this->c->ahmad, reportedSheet($this->c));

        expect(fn () => app(ReviewKpi::class)->handle($this->c->ahmad, $kpi, true))
            ->toThrow(AuthorizationException::class);
    });

    it('is refused to a team lead from another team', function () {
        $kpi = app(SubmitKpi::class)->handle($this->c->ahmad, reportedSheet($this->c));

        // Ahmad is Creative; this lead runs IT.
        expect(fn () => app(ReviewKpi::class)->handle($this->c->itLead, $kpi, true))
            ->toThrow(AuthorizationException::class);
    });

    it('is refused to a peer and to management', function () {
        $kpi = app(SubmitKpi::class)->handle($this->c->ahmad, reportedSheet($this->c));

        foreach ([$this->c->budi, $this->c->management, $this->c->requester] as $outsider) {
            expect(fn () => app(ReviewKpi::class)->handle($outsider, $kpi, true))
                ->toThrow(AuthorizationException::class);
        }
    });

    it('lets the team lead accept it', function () {
        $kpi = app(SubmitKpi::class)->handle($this->c->ahmad, reportedSheet($this->c));

        $kpi = app(ReviewKpi::class)->handle($this->c->creativeLead, $kpi, true);

        expect($kpi->status)->toBe(KpiStatus::Reviewed)
            ->and($kpi->reviewed_by)->toBe($this->c->creativeLead->id)
            ->and($kpi->reviewed_at)->not->toBeNull();
    });

    it('insists on a reason when sending the sheet back', function () {
        $kpi = app(SubmitKpi::class)->handle($this->c->ahmad, reportedSheet($this->c));

        expect(fn () => app(ReviewKpi::class)->handle($this->c->creativeLead, $kpi, false, '  '))
            ->toThrow(ValidationException::class);

        expect($kpi->fresh()->status)->toBe(KpiStatus::Submitted);
    });

    it('hands a sent-back sheet to the employee again without erasing the numbers', function () {
        $kpi = app(SubmitKpi::class)->handle($this->c->ahmad, reportedSheet($this->c));
        $before = (float) $kpi->final_score;

        $kpi = app(ReviewKpi::class)->handle($this->c->creativeLead, $kpi, false, 'Angka donasi belum sesuai laporan keuangan.');

        expect($kpi->status)->toBe(KpiStatus::Revision)
            ->and($kpi->review_note)->toBe('Angka donasi belum sesuai laporan keuangan.')
            ->and((float) $kpi->final_score)->toBe($before);

        // And the employee can edit once more, then send it back up.
        $kpi = app(UpdateKpiAchievements::class)->handle($this->c->ahmad, $kpi, [$kpi->items->first()->id => 55000]);
        $kpi = app(SubmitKpi::class)->handle($this->c->ahmad, $kpi);

        expect($kpi->status)->toBe(KpiStatus::Submitted)
            // A resubmission starts a clean review, so the old verdict is gone.
            ->and($kpi->reviewed_at)->toBeNull()
            ->and($kpi->reviewed_by)->toBeNull();
    });

    it('cannot be reviewed twice', function () {
        $kpi = app(SubmitKpi::class)->handle($this->c->ahmad, reportedSheet($this->c));
        app(ReviewKpi::class)->handle($this->c->creativeLead, $kpi, true);

        expect(fn () => app(ReviewKpi::class)->handle($this->c->creativeLead, $kpi->fresh(), true))
            ->toThrow(AuthorizationException::class);
    });

    it('lets an administrator review anyone', function () {
        $kpi = app(SubmitKpi::class)->handle($this->c->ahmad, reportedSheet($this->c));

        expect(app(ReviewKpi::class)->handle($this->c->admin, $kpi, true)->status)->toBe(KpiStatus::Reviewed);
    });
});

/** A sheet with every achievement reported, ready to submit. */
function reportedSheet(object $c): Kpi
{
    $kpi = sheetFor($c);
    $values = [];
    foreach ($kpi->items as $item) {
        $values[$item->id] = 50;
    }

    return app(UpdateKpiAchievements::class)->handle($c->ahmad, $kpi, $values);
}
