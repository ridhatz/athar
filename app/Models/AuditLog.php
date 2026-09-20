<?php

namespace App\Models;

use App\Exceptions\ImmutableRecordException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One administrative change: who did what to which record, with the values before and after.
 * Append-only (model guard here, database triggers in AuditGuards).
 *
 * @property int $id
 * @property int $actor_id
 * @property string $subject_type
 * @property int $subject_id
 * @property string $action
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property Carbon $created_at
 */
#[Fillable(['actor_id', 'subject_type', 'subject_id', 'action', 'old_values', 'new_values'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new ImmutableRecordException('Audit log entries cannot be modified.'));
        static::deleting(fn () => throw new ImmutableRecordException('Audit log entries cannot be deleted.'));
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
