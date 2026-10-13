<?php

namespace App\Concerns;

use App\Models\User;
use App\Services\AdminAudit;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared audit bookkeeping for the reference-data Actions: write only what really changed, and
 * name an activation or deactivation for what it is so the trail is readable without diffing.
 */
trait RecordsReferenceAudit
{
    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private function recordChange(
        AdminAudit $audit,
        User $actor,
        Model $subject,
        array $old,
        array $new,
        string $updatedAction,
        string $activatedAction,
        string $deactivatedAction,
    ): void {
        $changed = array_keys(array_filter($new, fn ($value, $key) => $value !== $old[$key], ARRAY_FILTER_USE_BOTH));

        if ($changed === []) {
            return;
        }

        $action = $old['is_active'] !== $new['is_active']
            ? ($new['is_active'] ? $activatedAction : $deactivatedAction)
            : $updatedAction;

        $audit->record(
            $actor,
            $subject,
            $action,
            array_intersect_key($old, array_flip($changed)),
            array_intersect_key($new, array_flip($changed)),
        );
    }
}
