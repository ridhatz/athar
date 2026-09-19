<?php

namespace App\Http\Resources;

use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The list-sized shape of a request. Web pages and the public API both use it, so
 * there is exactly one definition of what a request looks like to the outside.
 * Relations are only rendered when eager-loaded, to keep list queries flat.
 *
 * @mixin ServiceRequest
 */
class RequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_number' => $this->request_number,
            'title' => $this->title,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'deadline' => $this->deadline->toIso8601String(),
            'is_overdue' => $this->isOverdue(),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'slug' => $this->category->slug,
                'name' => $this->category->label(),
            ]),
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ]),
            'requester' => $this->whenLoaded('requester', fn () => [
                'id' => $this->requester->id,
                'name' => $this->requester->name,
            ]),
            'assigned_team' => $this->whenLoaded('assignedTeam', fn () => $this->assignedTeam ? [
                'id' => $this->assignedTeam->id,
                'name' => $this->assignedTeam->name,
            ] : null),
            'assigned_user' => $this->whenLoaded('assignedUser', fn () => $this->assignedUser ? [
                'id' => $this->assignedUser->id,
                'name' => $this->assignedUser->name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
        ];
    }
}
