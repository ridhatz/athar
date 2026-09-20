<?php

namespace App\Actions\Admin;

use App\Models\Role;
use App\Models\User;
use App\Services\AdminAudit;
use App\Services\UserRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Adds a person with the roles and teams that fit how they will use Athar.
 * Without a password one is generated; it is returned once and never stored in plain text.
 */
final class CreateUser
{
    public function __construct(
        private readonly UserRules $rules,
        private readonly AdminAudit $audit,
    ) {}

    /**
     * @param  array{name: string, email: string, password?: string|null, department_id?: int|null, roles: array<int, string>, team_ids?: array<int, int>, locale?: string|null}  $data
     * @return array{0: User, 1: string|null} the user, and the generated password (null if the admin chose one)
     */
    public function handle(User $actor, array $data): array
    {
        Gate::forUser($actor)->authorize('create', User::class);

        $teamIds = array_map('intval', $data['team_ids'] ?? []);
        $roles = $this->rules->assertCoherent($data['roles'], $teamIds);

        $generated = empty($data['password']) ? Str::password(14, symbols: false) : null;

        return DB::transaction(function () use ($actor, $data, $roles, $teamIds, $generated) {
            $user = User::query()->create([
                'name' => trim($data['name']),
                'email' => strtolower(trim($data['email'])),
                'password' => $generated ?? $data['password'],
                'department_id' => $data['department_id'] ?? null,
                'locale' => $data['locale'] ?? config('app.locale'),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            $user->roles()->sync(Role::query()->whereIn('code', array_map(fn ($r) => $r->value, $roles))->pluck('id')->all());
            $user->teams()->sync($teamIds);

            $this->audit->record($actor, $user, 'USER_CREATED', null, [
                'name' => $user->name,
                'email' => $user->email,
                'roles' => array_map(fn ($r) => $r->value, $roles),
                'teams' => $user->teams()->pluck('name')->all(),
                'department_id' => $user->department_id,
                'password_generated' => $generated !== null,
            ]);

            // Reload so database defaults (is_active, …) are present in what we hand back.
            return [$user->fresh(['roles', 'teams', 'department']), $generated];
        });
    }
}
