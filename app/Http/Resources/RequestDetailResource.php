<?php

namespace App\Http\Resources;

use App\Models\ServiceRequest;
use App\Workflow\RequestWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * A single request, plus what the caller is allowed to do with it right now.
 * Clients render buttons from `permissions` and `allowed_transitions`
 * instead of re-implementing the rules.
 *
 * @mixin ServiceRequest
 */
class RequestDetailResource extends RequestResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $gate = $user ? Gate::forUser($user) : null;

        return [
            ...parent::toArray($request),
            'description' => $this->description,
            'blocked_from_status' => $this->blocked_from_status?->value,
            'permissions' => [
                'edit_details' => $gate?->allows('editDetails', $this->resource) ?? false,
                'edit_priority' => $gate?->allows('editPriority', $this->resource) ?? false,
                'assign' => $gate?->allows('assign', $this->resource) ?? false,
                'view_internal' => $gate?->allows('viewInternal', $this->resource) ?? false,
                'comment' => $gate?->allows('comment', $this->resource) ?? false,
                'comment_internal' => $gate?->allows('commentInternal', $this->resource) ?? false,
                'attach' => $gate?->allows('attach', $this->resource) ?? false,
                'attach_work_product' => $gate?->allows('attachWorkProduct', $this->resource) ?? false,
                'review_decision' => $gate?->allows('reviewDecision', $this->resource) ?? false,
            ],
            'allowed_transitions' => $user ? RequestWorkflow::allowedFor($user, $this->resource) : [],
        ];
    }
}
