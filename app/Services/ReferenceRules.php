<?php

namespace App\Services;

use App\Enums\RoleCode;
use App\Models\RequestCategory;
use App\Models\Team;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * What must stay true of reference data for Athar to keep working.
 *
 * Reference rows are never deleted — history has to keep pointing at a real team, category and
 * department — so the only destructive move is switching `is_active` off, and these are the two
 * ways that move can break the system:
 *
 *  - Turning a team off can leave a STAFF/TEAM_LEAD with no active team at all. UserRules treats
 *    that person as incoherent: they would see nothing and could never be assigned anything.
 *  - Turning the last category off leaves nobody able to file a request, because the request form
 *    only offers active categories.
 */
final class ReferenceRules
{
    /**
     * Who would be left without any active team if this one were switched off.
     *
     * @return array<int, string> their names, empty when deactivating is safe
     */
    public function membersStrandedBy(Team $team): array
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('code', [RoleCode::Staff->value, RoleCode::TeamLead->value]))
            ->whereHas('teams', fn ($q) => $q->whereKey($team->id))
            ->whereDoesntHave('teams', fn ($q) => $q->where('is_active', true)->whereKeyNot($team->id))
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    /** @throws ValidationException */
    public function assertTeamCanBeDeactivated(Team $team): void
    {
        $stranded = $this->membersStrandedBy($team);

        if ($stranded !== []) {
            throw ValidationException::withMessages([
                'is_active' => __('errors.team_has_only_team_members', ['names' => implode(', ', $stranded)]),
            ]);
        }
    }

    /** @throws ValidationException */
    public function assertCategoryCanBeDeactivated(RequestCategory $category): void
    {
        $othersActive = RequestCategory::query()
            ->where('is_active', true)
            ->whereKeyNot($category->id)
            ->exists();

        if (! $othersActive) {
            throw ValidationException::withMessages([
                'is_active' => __('errors.category_last_active'),
            ]);
        }
    }
}
