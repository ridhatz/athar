<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'locale' => $this->locale,
            'department' => $this->department ? ['id' => $this->department->id, 'name' => $this->department->name] : null,
            'roles' => array_map(fn ($role) => $role->value, $this->roleCodes()),
            'teams' => $this->teams->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values(),
        ];
    }
}
