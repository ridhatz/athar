<?php

namespace App\Http\Resources;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A team as the administrator sees it. `stranded_members` is the warning the screen shows instead
 * of offering a switch-off that the Action would refuse anyway.
 */
class TeamAdminResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Team $team */
        $team = $this->resource;

        return [
            'id' => $team->id,
            'name' => $team->name,
            'description' => $team->description,
            'is_active' => $team->is_active,
            'members_count' => $team->members_count ?? $team->members()->count(),
            'stranded_members' => $this->whenNotNull($team->strandedMembers),
            'created_at' => $team->created_at?->toIso8601String(),
        ];
    }
}
