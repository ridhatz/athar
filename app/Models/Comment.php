<?php

namespace App\Models;

use App\Exceptions\ImmutableRecordException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One message in a request's discussion. Comments are not edited or deleted: what was said stays said.
 *
 * @property int $id
 * @property int $request_id
 * @property int $author_id
 * @property string $body
 * @property bool $is_internal
 * @property Carbon $created_at
 */
#[Fillable(['request_id', 'author_id', 'body', 'is_internal'])]
class Comment extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['is_internal' => 'boolean', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new ImmutableRecordException('Comments cannot be edited.'));
        static::deleting(fn () => throw new ImmutableRecordException('Comments cannot be deleted.'));
    }

    /** @return BelongsTo<ServiceRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'request_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return HasMany<Attachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }
}
