<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\RoleCode;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property int|null $department_id
 * @property string $locale
 * @property bool $is_active
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'department_id', 'locale'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /** @return BelongsToMany<Team, $this> */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class);
    }

    /** @return array<int, RoleCode> */
    public function roleCodes(): array
    {
        return $this->loadMissing('roles')->roles->map(fn (Role $role) => $role->code)->all();
    }

    public function hasRole(RoleCode $role): bool
    {
        return in_array($role, $this->roleCodes(), true);
    }

    public function hasAnyRole(RoleCode ...$roles): bool
    {
        return array_intersect(
            array_map(fn (RoleCode $r) => $r->value, $roles),
            array_map(fn (RoleCode $r) => $r->value, $this->roleCodes()),
        ) !== [];
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(RoleCode::Admin);
    }

    /** @return array<int, int> */
    public function teamIds(): array
    {
        return $this->loadMissing('teams')->teams->pluck('id')->all();
    }

    public function isInTeam(?int $teamId): bool
    {
        return $teamId !== null && in_array($teamId, $this->teamIds(), true);
    }

    /** Grant a role by code and drop the cached relation so checks see it immediately. */
    public function grantRole(RoleCode $code): static
    {
        $role = Role::query()->where('code', $code->value)->firstOrFail();
        $this->roles()->syncWithoutDetaching([$role->id]);
        $this->unsetRelation('roles');

        return $this;
    }

    public function joinTeam(Team $team): static
    {
        $this->teams()->syncWithoutDetaching([$team->id]);
        $this->unsetRelation('teams');

        return $this;
    }
}
