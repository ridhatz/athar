<?php

namespace App\Models;

use App\Enums\ActivityAction;
use App\Exceptions\ImmutableRecordException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One immutable fact about a request: who did what, and what changed.
 *
 * Append-only. Updating or deleting through Eloquent throws; the database
 * additionally blocks UPDATE/DELETE with triggers where the server allows them.
 *
 * old_value / new_value hold human-readable snapshots (status codes, team and person
 * names) so history stays legible even if a name is edited later.
 *
 * @property int $id
 * @property int $request_id
 * @property int|null $actor_id
 * @property ActivityAction $action
 * @property string|null $field
 * @property string|null $old_value
 * @property string|null $new_value
 * @property string|null $note
 * @property bool $is_internal
 * @property Carbon $created_at
 */
#[Fillable(['request_id', 'actor_id', 'action', 'field', 'old_value', 'new_value', 'note', 'is_internal'])]
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'action' => ActivityAction::class,
            'is_internal' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new ImmutableRecordException('Activity log entries are append-only and cannot be modified.');
        });

        static::deleting(function () {
            throw new ImmutableRecordException('Activity log entries are append-only and cannot be deleted.');
        });
    }

    /** @return BelongsTo<ServiceRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'request_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
