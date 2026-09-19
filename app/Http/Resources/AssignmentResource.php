<?php

namespace App\Http\Resources;

use App\Models\Assignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Assignment */
class AssignmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'team' => $this->whenLoaded('team', fn () => $this->team ? ['id' => $this->team->id, 'name' => $this->team->name] : null),
            'user' => $this->whenLoaded('user', fn () => $this->user ? ['id' => $this->user->id, 'name' => $this->user->name] : null),
            'assigned_by' => $this->whenLoaded('assigner', fn () => ['id' => $this->assigner->id, 'name' => $this->assigner->name]),
            'note' => $this->note,
            'is_current' => (bool) $this->is_current,
            'assigned_at' => $this->assigned_at->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
        ];
    }
}
