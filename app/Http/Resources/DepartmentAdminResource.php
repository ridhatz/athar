<?php

namespace App\Http\Resources;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A department as the administrator sees it. */
class DepartmentAdminResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Department $department */
        $department = $this->resource;

        return [
            'id' => $department->id,
            'name' => $department->name,
            'is_active' => $department->is_active,
            'users_count' => $department->users_count ?? $department->users()->count(),
            'created_at' => $department->created_at?->toIso8601String(),
        ];
    }
}
