<?php

namespace App\Actions\Kpi;

use App\Models\KpiTemplate;
use App\Models\KpiTemplateItem;
use App\Models\User;
use App\Services\AdminAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Writes a team's KPI blueprint: the template and its whole list of lines in one go.
 *
 * The lines are replaced rather than merged, which is safe precisely because a template is not a
 * record: generating a month COPIES these values onto each sheet, so rewriting a template never
 * reaches back and alters a KPI somebody has already been scored against.
 *
 * One template per team, enforced here as well as by the screen, because two blueprints for the
 * same team would make generation pick one arbitrarily.
 */
final class SaveKpiTemplate
{
    public function __construct(private readonly AdminAudit $audit) {}

    /**
     * @param  array{team_id?: int|null, name: string, is_active?: bool, items: array<int, array{name: string, target_value: float|int|string, direction: string, unit?: string|null, weight: float|int|string, frequency?: string|null}>}  $data
     */
    public function handle(User $actor, ?KpiTemplate $template, array $data): KpiTemplate
    {
        Gate::forUser($actor)->authorize($template === null ? 'create' : 'update', KpiTemplate::class);

        return DB::transaction(function () use ($actor, $template, $data) {
            $teamId = $data['team_id'] ?? null;

            $clash = KpiTemplate::query()
                ->where('team_id', $teamId)
                ->when($template !== null, fn ($q) => $q->whereKeyNot($template->id))
                ->exists();

            if ($clash) {
                throw ValidationException::withMessages([
                    'team_id' => __('errors.kpi_template_team_taken'),
                ]);
            }

            $isNew = $template === null;

            $template = $template ?? new KpiTemplate;
            $template->team_id = $teamId;
            $template->name = trim($data['name']);
            $template->is_active = $data['is_active'] ?? true;
            $template->save();

            $template->items()->delete();

            foreach (array_values($data['items']) as $i => $line) {
                KpiTemplateItem::query()->create([
                    'kpi_template_id' => $template->id,
                    'name' => trim($line['name']),
                    'target_value' => $line['target_value'],
                    'direction' => $line['direction'],
                    'unit' => ($line['unit'] ?? '') === '' ? null : $line['unit'],
                    'weight' => $line['weight'],
                    'frequency' => $line['frequency'] ?? 'MONTHLY',
                    'sort_order' => $i,
                ]);
            }

            $this->audit->record($actor, $template, $isNew ? 'KPI_TEMPLATE_CREATED' : 'KPI_TEMPLATE_UPDATED', null, [
                'name' => $template->name,
                'team_id' => $template->team_id,
                'items' => count($data['items']),
                'total_weight' => array_sum(array_map(fn ($l) => (float) $l['weight'], $data['items'])),
            ]);

            return $template->load('items', 'team');
        });
    }
}
