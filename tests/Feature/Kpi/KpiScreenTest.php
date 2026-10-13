<?php

use App\Actions\Kpi\SubmitKpi;
use App\Actions\Kpi\UpdateKpiAchievements;
use App\Enums\KpiDirection;
use App\Models\Kpi;
use App\Models\KpiItem;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->c = cast();
});

/** A filled sheet for the given person, opened straight on the models. */
function sheet(object $c, $owner, string $period = '2026-09', ?float $achievement = null): Kpi
{
    $kpi = Kpi::factory()->forPeriod($period)->create(['user_id' => $owner->id]);
    KpiItem::factory()->for($kpi)->create([
        'name' => 'Jumlah pendaftar baru',
        'target_value' => 100,
        'direction' => KpiDirection::HigherBetter,
        'weight' => 100,
    ]);

    if ($achievement !== null) {
        app(UpdateKpiAchievements::class)->handle($owner, $kpi, [$kpi->items->first()->id => $achievement]);
    }

    return $kpi->fresh(['items']);
}

describe('who sees whose sheet', function () {
    it('shows a staff member only their own', function () {
        sheet($this->c, $this->c->ahmad);
        sheet($this->c, $this->c->budi);
        sheet($this->c, $this->c->dewi);

        $this->actingAs($this->c->ahmad)->get('/kpi')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('kpi/index')
                ->has('kpis.data', 1)
                ->where('kpis.data.0.user.name', 'Ahmad')
                // Nothing to generate, nobody else to open sheets for.
                ->where('canCreate', false)
        );
    });

    it('shows a team lead their own team, not another one', function () {
        sheet($this->c, $this->c->ahmad);   // Creative
        sheet($this->c, $this->c->budi);    // Creative
        sheet($this->c, $this->c->dewi);    // IT

        $this->actingAs($this->c->creativeLead)->get('/kpi')->assertOk()->assertInertia(
            fn (Assert $page) => $page->has('kpis.data', 2)
        );
    });

    it('shows an administrator everyone', function () {
        sheet($this->c, $this->c->ahmad);
        sheet($this->c, $this->c->dewi);

        $this->actingAs($this->c->admin)->get('/kpi')->assertOk()->assertInertia(
            fn (Assert $page) => $page->has('kpis.data', 2)->where('canCreate', true)
        );
    });

    it('never leaks another person\'s sheet through the period filter', function () {
        sheet($this->c, $this->c->dewi, '2026-09');

        $this->actingAs($this->c->ahmad)->get('/kpi?period=2026-09')->assertOk()->assertInertia(
            fn (Assert $page) => $page->has('kpis.data', 0)
        );
    });
});

describe('what the screen offers', function () {
    it('offers the owner the achievement boxes and nobody the review buttons yet', function () {
        sheet($this->c, $this->c->ahmad);

        $this->actingAs($this->c->ahmad)->get('/kpi')->assertInertia(
            fn (Assert $page) => $page
                ->where('kpis.data.0.can.fill', true)
                ->where('kpis.data.0.can.review', false)
        );
    });

    it('offers the team lead the review buttons once it is submitted, and not before', function () {
        $kpi = sheet($this->c, $this->c->ahmad, '2026-09', 100);

        $this->actingAs($this->c->creativeLead)->get('/kpi')->assertInertia(
            fn (Assert $page) => $page->where('kpis.data.0.can.review', false)
        );

        app(SubmitKpi::class)->handle($this->c->ahmad, $kpi);

        $this->actingAs($this->c->creativeLead)->get('/kpi')->assertInertia(
            fn (Assert $page) => $page
                ->where('kpis.data.0.can.review', true)
                // The numbers are frozen now, even for the person who reported them.
                ->where('kpis.data.0.can.fill', false)
        );
    });

    it('reports progress and score the way the list draws them', function () {
        $kpi = sheet($this->c, $this->c->ahmad, '2026-09');
        KpiItem::factory()->for($kpi)->create(['target_value' => 100, 'weight' => 100]);
        app(UpdateKpiAchievements::class)->handle($this->c->ahmad, $kpi, [$kpi->items->first()->id => 120]);

        $this->actingAs($this->c->ahmad)->get('/kpi')->assertInertia(
            fn (Assert $page) => $page
                // One of two KPIs reported.
                ->where('kpis.data.0.progress', 50)
                ->where('kpis.data.0.final_score', 120)
        );
    });
});

describe('the achievement endpoint', function () {
    it('refuses somebody else\'s sheet over HTTP, not just in the Action', function () {
        $kpi = sheet($this->c, $this->c->ahmad);

        $this->actingAs($this->c->budi)
            ->patch("/kpi/{$kpi->id}/achievements", ['achievements' => [$kpi->items->first()->id => 99]])
            ->assertForbidden();

        expect($kpi->items->first()->fresh()->achievement)->toBeNull();
    });

    it('takes a cleared box as "not reported", not as zero', function () {
        $kpi = sheet($this->c, $this->c->ahmad, '2026-09', 80);
        expect($kpi->fresh()->final_score)->not->toBeNull();

        $this->actingAs($this->c->ahmad)
            ->patch("/kpi/{$kpi->id}/achievements", ['achievements' => [$kpi->items->first()->id => null]])
            ->assertRedirect();

        expect($kpi->fresh()->final_score)->toBeNull()
            ->and($kpi->items->first()->fresh()->achievement)->toBeNull();
    });
});

describe('the analysis screen', function () {
    it('counts only what the viewer is allowed to see', function () {
        sheet($this->c, $this->c->ahmad, '2026-09', 100);
        sheet($this->c, $this->c->dewi, '2026-09', 100);

        $this->actingAs($this->c->ahmad)->get('/kpi/analysis?year=2026')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('kpi/analysis')
                ->where('stats.total', 1)
                ->where('stats.people', 1)
        );

        $this->actingAs($this->c->admin)->get('/kpi/analysis?year=2026')->assertInertia(
            fn (Assert $page) => $page->where('stats.total', 2)->where('stats.people', 2)
        );
    });

    it('gives twelve trend points whether or not every month has data', function () {
        sheet($this->c, $this->c->ahmad, '2026-09', 100);

        $this->actingAs($this->c->admin)->get('/kpi/analysis?year=2026')->assertInertia(
            fn (Assert $page) => $page
                ->has('trend', 12)
                // September has a score; a month nobody was scored in stays null, never 0,
                // so the chart shows a gap instead of a crash to the floor.
                ->where('trend.8.average', 100)
                ->where('trend.0.average', null)
        );
    });

    it('narrows by department, which is what the Lembaga filter maps to', function () {
        // cast() leaves the workers without a department, so put one on record first.
        $this->c->ahmad->update(['department_id' => $this->c->department->id]);

        sheet($this->c, $this->c->ahmad, '2026-09', 100);   // Humas
        sheet($this->c, $this->c->dewi, '2026-09', 100);    // no department

        $this->actingAs($this->c->admin)
            ->get("/kpi/analysis?year=2026&department={$this->c->department->id}")
            ->assertInertia(fn (Assert $page) => $page->where('stats.total', 1));
    });
});
