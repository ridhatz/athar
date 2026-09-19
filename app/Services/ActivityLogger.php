<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\ServiceRequest;
use App\Models\User;

/**
 * The only writer of the audit trail. Call it from the same transaction as the change
 * it describes, so a change and its record either both happen or neither does.
 */
final class ActivityLogger
{
    public function record(
        ServiceRequest $request,
        ?User $actor,
        ActivityAction $action,
        ?string $field = null,
        ?string $old = null,
        ?string $new = null,
        ?string $note = null,
        bool $internal = false,
    ): ActivityLog {
        return ActivityLog::query()->create([
            'request_id' => $request->id,
            'actor_id' => $actor?->id,
            'action' => $action,
            'field' => $field,
            'old_value' => $old,
            'new_value' => $new,
            'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            'is_internal' => $internal,
        ]);
    }
}
