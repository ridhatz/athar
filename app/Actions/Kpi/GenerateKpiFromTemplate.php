<?php

namespace App\Actions\Kpi;

use App\Enums\KpiStatus;
use App\Enums\RoleCode;
use App\Models\Kpi;
use App\Models\KpiItem;
use App\Models\KpiTemplate;
use App\Models\User;
use App\Services\AdminAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Opens a month's sheets for everyone at once, from their team's template.
 *
 * Athar has no job title, so the template hangs off the TEAM — Admisi and Media do measurably
 * different work. A template with no team is the fallback for anyone whose team has none.
 *
 * Nobody is ever overwritten: a person who already has a sheet for the month is skipped, so
 * running this twice is safe and never wipes reported achievements.
 */
final class GenerateKpiFromTemplate
{
    public function __construct(private readonly AdminAudit $audit) {}

    /**
     * @return array{created: int, skipped: int, without_template: array<int, string>}
     */
    public function handle(User $actor, string $period, ?string $dueDate = null): array
    {
        Gate::forUser($actor)->authorize('create', Kpi::class);

        $period = date('Y-m-01', strtotime($period.'-01'));
        $due = $dueDate ?: date('Y-m-d', strtotime($period.' +1 month'));

        $templates = KpiTemplate::query()->active()->with('items')->get();

        if ($templates->isEmpty()) {
            throw ValidationException::withMessages(['period' => __('errors.kpi_no_template')]);
        }

        $byTeam = $templates->whereNotNull('team_id')->keyBy('team_id');
        $fallback = $templates->firstWhere('team_id', null);

        // Only people who actually do work get a sheet; requesters and management are not scored.
        $people = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('code', [RoleCode::Staff->value, RoleCode::TeamLead->value]))
            ->with('teams')
            ->orderBy('name')
            ->get();

        $created = 0;
        $skipped = 0;
        $withoutTemplate = [];

        DB::transaction(function () use ($actor, $people, $byTeam, $fallback, $period, $due, &$created, &$skipped, &$withoutTemplate) {
            foreach ($people as $person) {
                if (Kpi::query()->where('user_id', $person->id)->whereDate('period', $period)->exists()) {
                    $skipped++;

                    continue;
                }

                $template = null;
                foreach ($person->teams as $team) {
                    if ($byTeam->has($team->id)) {
                        $template = $byTeam->get($team->id);
                        break;
                    }
                }
                $template ??= $fallback;

                if ($template === null || $template->items->isEmpty()) {
                    $withoutTemplate[] = $person->name;

                    continue;
                }

                $kpi = Kpi::query()->create([
                    'user_id' => $person->id,
                    'period' => $period,
                    'due_date' => $due,
                    'status' => KpiStatus::Pending,
                    'created_by' => $actor->id,
                ]);

                foreach ($template->items as $i => $line) {
                    KpiItem::query()->create([
                        'kpi_id' => $kpi->id,
                        'name' => $line->name,
                        'target_value' => $line->target_value,
                        'direction' => $line->direction,
                        'unit' => $line->unit,
                        'weight' => $line->weight,
                        'frequency' => $line->frequency,
                        'sort_order' => $i,
                    ]);
                }

                $created++;
            }

            // A generation run has no single subject row, so the trail hangs off whoever ran it.
            $this->audit->record($actor, $actor, 'KPI_GENERATED', null, [
                'period' => substr($period, 0, 7),
                'created' => $created,
                'skipped' => $skipped,
            ]);
        });

        return ['created' => $created, 'skipped' => $skipped, 'without_template' => $withoutTemplate];
    }
}
