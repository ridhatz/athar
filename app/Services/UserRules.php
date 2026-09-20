<?php

namespace App\Services;

use App\Enums\RoleCode;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * What makes a set of roles and teams coherent for a person in Athar.
 *
 *  - at least one role;
 *  - MANAGEMENT is observe-only, so it is never combined with a role that can act;
 *  - people who do work (STAFF, TEAM_LEAD) belong to at least one team, otherwise they would
 *    see nothing and could never be assigned anything;
 *  - teams must exist and be active.
 */
final class UserRules
{
    /**
     * @param  array<int, string>  $roleCodes
     * @param  array<int, int>  $teamIds
     * @return array<int, RoleCode>
     *
     * @throws ValidationException
     */
    public function assertCoherent(array $roleCodes, array $teamIds): array
    {
        $roles = array_values(array_unique(array_map(fn (string $c) => RoleCode::from($c), $roleCodes), SORT_REGULAR));

        if ($roles === []) {
            $this->fail('roles', __('errors.user_roles_required'));
        }

        if (in_array(RoleCode::Management, $roles, true) && count($roles) > 1) {
            $this->fail('roles', __('errors.user_management_exclusive'));
        }

        $works = in_array(RoleCode::Staff, $roles, true) || in_array(RoleCode::TeamLead, $roles, true);

        if ($works && $teamIds === []) {
            $this->fail('team_ids', __('errors.user_team_required'));
        }

        $teamIds = array_values(array_unique($teamIds));

        if ($teamIds !== [] && Team::query()->where('is_active', true)->whereIn('id', $teamIds)->count() !== count($teamIds)) {
            $this->fail('team_ids', __('errors.team_invalid'));
        }

        return $roles;
    }

    /** Is there another active administrator besides this person? */
    public function anotherAdminExists(User $except): bool
    {
        return User::query()
            ->where('is_active', true)
            ->whereKeyNot($except->id)
            ->whereHas('roles', fn ($q) => $q->where('code', RoleCode::Admin->value))
            ->exists();
    }

    /** Cut a person off everywhere: API tokens and web sessions. */
    public function revokeAccess(User $user): void
    {
        $user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
