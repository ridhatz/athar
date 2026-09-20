<?php

namespace App\Actions\Admin;

use App\Enums\RoleCode;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminAudit;
use App\Services\UserRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Changes who a person is in Athar: name, email, department, roles, teams, language, and whether
 * the account is active. Only what really changes is written, and the before/after goes to the audit trail.
 *
 * Guard rails: you cannot lock yourself out, and the system can never be left without an active administrator.
 */
final class UpdateUser
{
    public function __construct(
        private readonly UserRules $rules,
        private readonly AdminAudit $audit,
    ) {}

    /**
     * @param  array{name?: string, email?: string, department_id?: int|null, roles?: array<int, string>, team_ids?: array<int, int>, locale?: string, is_active?: bool}  $data
     */
    public function handle(User $actor, User $subject, array $data): User
    {
        Gate::forUser($actor)->authorize('update', $subject);

        return DB::transaction(function () use ($actor, $subject, $data) {
            $subject = User::query()->whereKey($subject->id)->lockForUpdate()->firstOrFail();

            $old = $this->snapshot($subject);

            $roleCodes = $data['roles'] ?? array_map(fn ($r) => $r->value, $subject->roleCodes());
            $teamIds = array_map('intval', $data['team_ids'] ?? $subject->teamIds());
            $roles = $this->rules->assertCoherent($roleCodes, $teamIds);

            $active = $data['is_active'] ?? $subject->is_active;
            $stillAdmin = in_array(RoleCode::Admin, $roles, true);

            if ($subject->id === $actor->id && ! $active) {
                throw ValidationException::withMessages(['is_active' => __('errors.cannot_deactivate_self')]);
            }

            if ($subject->isAdmin() && $subject->is_active && (! $stillAdmin || ! $active) && ! $this->rules->anotherAdminExists($subject)) {
                throw ValidationException::withMessages(['roles' => __('errors.last_admin')]);
            }

            $subject->fill(array_intersect_key($data, array_flip(['name', 'email', 'department_id', 'locale'])));
            if (isset($data['email'])) {
                $subject->email = strtolower(trim($data['email']));
            }
            $subject->is_active = $active;
            $subject->save();

            $subject->roles()->sync(Role::query()->whereIn('code', array_map(fn ($r) => $r->value, $roles))->pluck('id')->all());
            $subject->teams()->sync(array_values(array_unique($teamIds)));
            $subject->unsetRelation('roles')->unsetRelation('teams')->unsetRelation('department');

            $new = $this->snapshot($subject);
            $changed = array_keys(array_filter($new, fn ($value, $key) => $value !== $old[$key], ARRAY_FILTER_USE_BOTH));

            if ($changed !== []) {
                $this->audit->record(
                    $actor,
                    $subject,
                    $old['is_active'] !== $new['is_active'] ? ($new['is_active'] ? 'USER_ACTIVATED' : 'USER_DEACTIVATED') : 'USER_UPDATED',
                    array_intersect_key($old, array_flip($changed)),
                    array_intersect_key($new, array_flip($changed)),
                );
            }

            // A deactivated person is out immediately: tokens and sessions go with the account.
            if ($old['is_active'] && ! $new['is_active']) {
                $this->rules->revokeAccess($subject);
            }

            return $subject->load('roles', 'teams', 'department');
        });
    }

    /** @return array<string, mixed> */
    private function snapshot(User $user): array
    {
        $user->unsetRelation('roles')->unsetRelation('teams')->unsetRelation('department');

        return [
            'name' => $user->name,
            'email' => $user->email,
            'department' => $user->department?->name,
            'roles' => collect($user->roleCodes())->map(fn ($r) => $r->value)->sort()->values()->all(),
            'teams' => $user->teams()->orderBy('name')->pluck('name')->all(),
            'locale' => $user->locale,
            'is_active' => (bool) $user->is_active,
        ];
    }
}
