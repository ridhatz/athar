<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** The only writer of the administrative audit trail. Never pass secrets in. */
final class AdminAudit
{
    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function record(User $actor, Model $subject, string $action, ?array $old = null, ?array $new = null): AuditLog
    {
        return AuditLog::query()->create([
            'actor_id' => $actor->id,
            'subject_type' => class_basename($subject),
            'subject_id' => $subject->getKey(),
            'action' => $action,
            'old_values' => $old,
            'new_values' => $new,
        ]);
    }
}
