<?php

namespace App\Actions\Admin;

use App\Concerns\RecordsReferenceAudit;
use App\Models\Team;
use App\Models\User;
use App\Services\AdminAudit;
use App\Services\ReferenceRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Renames a team, rewrites its description, or switches it off.
 *
 * Switching off is refused when it would leave a staff member or team lead without any active
 * team, because such a person can neither see work nor be assigned any (see ReferenceRules).
 */
final class UpdateTeam
{
    use RecordsReferenceAudit;

    public function __construct(
        private readonly ReferenceRules $rules,
        private readonly AdminAudit $audit,
    ) {}

    /** @param array{name?: string, description?: string|null, is_active?: bool} $data */
    public function handle(User $actor, Team $team, array $data): Team
    {
        Gate::forUser($actor)->authorize('update', $team);

        return DB::transaction(function () use ($actor, $team, $data) {
            $team = Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();

            $old = $this->snapshot($team);

            if (($data['is_active'] ?? $team->is_active) === false && $team->is_active) {
                $this->rules->assertTeamCanBeDeactivated($team);
            }

            if (isset($data['name'])) {
                $team->name = trim($data['name']);
            }
            if (array_key_exists('description', $data)) {
                $description = trim((string) $data['description']);
                $team->description = $description === '' ? null : $description;
            }
            $team->is_active = $data['is_active'] ?? $team->is_active;
            $team->save();

            $this->recordChange(
                $this->audit, $actor, $team, $old, $this->snapshot($team),
                'TEAM_UPDATED', 'TEAM_ACTIVATED', 'TEAM_DEACTIVATED',
            );

            return $team;
        });
    }

    /** @return array<string, mixed> */
    private function snapshot(Team $team): array
    {
        return [
            'name' => $team->name,
            'description' => $team->description,
            'is_active' => (bool) $team->is_active,
        ];
    }
}
