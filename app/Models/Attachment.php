<?php

namespace App\Models;

use App\Enums\AttachmentKind;
use App\Exceptions\ImmutableRecordException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * File metadata. The bytes live on a private disk under a generated name; the name the uploader
 * chose is kept only for display and download. Never replaced or deleted: re-uploading the same
 * name creates the next version.
 *
 * @property int $id
 * @property int $request_id
 * @property int|null $comment_id
 * @property int $uploader_id
 * @property AttachmentKind $kind
 * @property string $original_name
 * @property string $stored_path
 * @property string $mime_type
 * @property int $size
 * @property string $sha256
 * @property int $version
 * @property bool $is_internal
 * @property Carbon $created_at
 */
#[Fillable(['request_id', 'comment_id', 'uploader_id', 'kind', 'original_name', 'stored_path', 'mime_type', 'size', 'sha256', 'version', 'is_internal'])]
class Attachment extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'kind' => AttachmentKind::class,
            'is_internal' => 'boolean',
            'size' => 'integer',
            'version' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new ImmutableRecordException('Attachments cannot be modified.'));
        static::deleting(fn () => throw new ImmutableRecordException('Attachments cannot be deleted.'));
    }

    /** @return BelongsTo<ServiceRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'request_id');
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }
}
