<?php

use App\Actions\Kpi\GenerateKpiFromTemplate;
use App\Enums\KpiDirection;
use App\Models\AuditLog;
use App\Models\Kpi;
use App\Models\KpiTemplate;
use App\Models\KpiTemplateItem;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->c = cast();
});

/** A valid payload for the template screen. */
function templatePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'KPI Creative',
        'team_id' => test()->c->creative->id,
        'items' => [
            ['name' => 'Jumlah konten terbit', 'target_value' => 40,
                'direction' => KpiDirection::HigherBetter->value, 'weight' => 60],
            ['name' => 'Konten terlambat', 'target_value' => 5, 'unit' => '%',
                'direction' => KpiDirection::LowerBetter->value, 'weight' => 40],
        ],
    ], $overrides);
}

describe('who may shape a template', function () {
    it('is the administrator, and nobody else', function () {
        foreach (['ahmad', 'creativeLead', 'management', 'requester'] as $who) {
            $this->actingAs($this->c->{$who})->get('/kpi/templates')->assertForbidden();
            $this->actingAs($this->c->{$who})->post('/kpi/templates', templatePayload())->assertForbidden();
        }

        expect(KpiTemplate::query()->count())->toBe(0);
    });

    it('lets an administrator open the screen', function () {
        $this->actingAs($this->c->admin)->get('/kpi/templates')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('kpi/templates')->has('teams')
        );
    });

    /**
     * The screen maps straight over `templates`, so it must arrive as a plain list. A bare
     * resource collection would arrive wrapped in {"data": …} and render a blank page — which no
     * amount of asserting on `teams` would have caught.
     */
    it('hands the screen a plain list, not a wrapped collection', function () {
        $this->actingAs($this->c->admin)->post('/kpi/templates', templatePayload());

        $this->actingAs($this->c->admin)->get('/kpi/templates')->assertInertia(
            fn (Assert $page) => $page
                ->has('templates', 1)
                ->where('templates.0.name', 'KPI Creative')
                ->where('templates.0.team.name', 'Creative')
                ->where('templates.0.total_weight', 100)
                ->has('templates.0.items', 2)
        );
    });
});

describe('writing a template', function () {
    it('saves the blueprint and records it', function () {
        $this->actingAs($this->c->admin)->post('/kpi/templates', templatePayload())->assertRedirect();

        $template = KpiTemplate::query()->with('items')->sole();

        expect($template->name)->toBe('KPI Creative')
            ->and($template->team_id)->toBe($this->c->creative->id)
            ->and($template->is_active)->toBeTrue()
            ->and($template->items)->toHaveCount(2)
            ->and((float) $template->items->sum('weight'))->toBe(100.0);

        expect(AuditLog::query()->where('action', 'KPI_TEMPLATE_CREATED')->exists())->toBeTrue();
    });

    it('keeps the order the administrator wrote the lines in', function () {
        $this->actingAs($this->c->admin)->post('/kpi/templates', templatePayload());

        expect(KpiTemplate::query()->sole()->items->pluck('name')->all())
            ->toBe(['Jumlah konten terbit', 'Konten terlambat']);
    });

    it('refuses a second template for the same team', function () {
        $this->actingAs($this->c->admin)->post('/kpi/templates', templatePayload());

        $this->actingAs($this->c->admin)
            ->post('/kpi/templates', templatePayload(['name' => 'Duplikat']))
            ->assertSessionHasErrors('team_id');

        expect(KpiTemplate::query()->count())->toBe(1);
    });

    it('allows exactly one fallback template for teams without their own', function () {
        $this->actingAs($this->c->admin)->post('/kpi/templates', templatePayload(['team_id' => null]))->assertRedirect();

        $this->actingAs($this->c->admin)
            ->post('/kpi/templates', templatePayload(['team_id' => null, 'name' => 'Umum kedua']))
            ->assertSessionHasErrors('team_id');
    });

    it('rejects a weight outside 0–100', function () {
        $payload = templatePayload();
        $payload['items'][0]['weight'] = 150;

        $this->actingAs($this->c->admin)->post('/kpi/templates', $payload)
            ->assertSessionHasErrors('items.0.weight');
    });

    it('insists on at least one KPI', function () {
        $this->actingAs($this->c->admin)->post('/kpi/templates', templatePayload(['items' => []]))
            ->assertSessionHasErrors('items');
    });
});

describe('editing a template', function () {
    it('replaces the whole list rather than merging it', function () {
        $this->actingAs($this->c->admin)->post('/kpi/templates', templatePayload());
        $template = KpiTemplate::query()->sole();

        $this->actingAs($this->c->admin)->patch("/kpi/templates/{$template->id}", templatePayload([
            'items' => [['name' => 'Satu-satunya KPI', 'target_value' => 10,
                'direction' => KpiDirection::HigherBetter->value, 'weight' => 100]],
        ]))->assertRedirect();

        expect($template->fresh()->items->pluck('name')->all())->toBe(['Satu-satunya KPI']);
        expect(KpiTemplateItem::query()->count())->toBe(1);
    });

    it('lets a template keep its own team without tripping the clash check', function () {
        $this->actingAs($this->c->admin)->post('/kpi/templates', templatePayload());
        $template = KpiTemplate::query()->sole();

        $this->actingAs($this->c->admin)
            ->patch("/kpi/templates/{$template->id}", templatePayload(['name' => 'Nama baru']))
            ->assertSessionHasNoErrors();

        expect($template->fresh()->name)->toBe('Nama baru');
    });

    /**
     * The point of templates being blueprints: they are copied, not referenced, so correcting a
     * blueprint can never rewrite a KPI somebody has already been scored against.
     */
    it('never reaches back into sheets already generated from it', function () {
        $this->actingAs($this->c->admin)->post('/kpi/templates', templatePayload());
        $template = KpiTemplate::query()->sole();

        app(GenerateKpiFromTemplate::class)->handle($this->c->admin, '2026-09');
        $sheet = Kpi::query()->where('user_id', $this->c->ahmad->id)->with('items')->sole();
        $before = $sheet->items->pluck('name')->all();

        $this->actingAs($this->c->admin)->patch("/kpi/templates/{$template->id}", templatePayload([
            'items' => [['name' => 'KPI yang benar-benar berbeda', 'target_value' => 1,
                'direction' => KpiDirection::HigherBetter->value, 'weight' => 100]],
        ]));

        expect($sheet->fresh()->items->pluck('name')->all())->toBe($before);
    });
});

describe('generating from templates', function () {
    it('gives each person the blueprint of their own team', function () {
        $this->actingAs($this->c->admin)->post('/kpi/templates', templatePayload());          // Creative
        $this->actingAs($this->c->admin)->post('/kpi/templates', templatePayload([
            'name' => 'KPI IT', 'team_id' => $this->c->it->id,
            'items' => [['name' => 'Uptime layanan', 'target_value' => 99,
                'direction' => KpiDirection::HigherBetter->value, 'weight' => 100]],
        ]));

        app(GenerateKpiFromTemplate::class)->handle($this->c->admin, '2026-09');

        $ahmad = Kpi::query()->where('user_id', $this->c->ahmad->id)->with('items')->sole();
        $dewi = Kpi::query()->where('user_id', $this->c->dewi->id)->with('items')->sole();

        expect($ahmad->items)->toHaveCount(2)
            ->and($dewi->items->pluck('name')->all())->toBe(['Uptime layanan']);
    });

    it('falls back to the teamless template for a team with none of its own', function () {
        $this->actingAs($this->c->admin)->post('/kpi/templates', templatePayload([
            'name' => 'Umum', 'team_id' => null,
        ]));

        app(GenerateKpiFromTemplate::class)->handle($this->c->admin, '2026-09');

        expect(Kpi::query()->where('user_id', $this->c->dewi->id)->sole()->items)->toHaveCount(2);
    });

    it('refuses to run when there is no template at all', function () {
        expect(fn () => app(GenerateKpiFromTemplate::class)->handle($this->c->admin, '2026-09'))
            ->toThrow(ValidationException::class);
    });

    it('skips anyone who already has a sheet, so a second run is safe', function () {
        $this->actingAs($this->c->admin)->post('/kpi/templates', templatePayload(['team_id' => null]));

        $first = app(GenerateKpiFromTemplate::class)->handle($this->c->admin, '2026-09');
        $second = app(GenerateKpiFromTemplate::class)->handle($this->c->admin, '2026-09');

        expect($first['created'])->toBeGreaterThan(0)
            ->and($second['created'])->toBe(0)
            ->and($second['skipped'])->toBe($first['created']);
    });

    it('opens sheets for workers only, never for requesters or management', function () {
        $this->actingAs($this->c->admin)->post('/kpi/templates', templatePayload(['team_id' => null]));

        app(GenerateKpiFromTemplate::class)->handle($this->c->admin, '2026-09');

        expect(Kpi::query()->where('user_id', $this->c->requester->id)->exists())->toBeFalse()
            ->and(Kpi::query()->where('user_id', $this->c->management->id)->exists())->toBeFalse()
            ->and(Kpi::query()->where('user_id', $this->c->ahmad->id)->exists())->toBeTrue();
    });
});
