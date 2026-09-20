<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A person as the administrator sees them. Never includes the password hash or secrets. */
class UserAdminResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'locale' => $user->locale,
            'department' => $user->department ? ['id' => $user->department->id, 'name' => $user->department->name] : null,
            'roles' => array_map(fn ($role) => $role->value, $user->roleCodes()),
            'teams' => $user->teams->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
