<?php

namespace App\Http\Resources;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Attachment */
class AttachmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'version' => $this->version,
            'is_internal' => $this->is_internal,
            'comment_id' => $this->comment_id,
            'uploader' => $this->whenLoaded('uploader', fn () => ['id' => $this->uploader->id, 'name' => $this->uploader->name]),
            'created_at' => $this->created_at->toIso8601String(),
            // Always the authorised route: files are never reachable by a direct path.
            'download_url' => route($request->is('api/*') ? 'api.v1.requests.attachments.download' : 'requests.attachments.download', [$this->request_id, $this->id], false),
        ];
    }
}
