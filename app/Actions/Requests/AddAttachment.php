<?php

namespace App\Actions\Requests;

use App\Enums\ActivityAction;
use App\Enums\AttachmentKind;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\FileGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Attach a file to a request. Nothing is ever replaced: uploading a file whose name already
 * exists on the request records it as the next version.
 */
final class AddAttachment
{
    public function __construct(
        private readonly FileGuard $guard,
        private readonly ActivityLogger $log,
    ) {}

    public function handle(
        User $actor,
        ServiceRequest $request,
        UploadedFile $file,
        AttachmentKind $kind = AttachmentKind::Other,
        bool $internal = false,
        ?Comment $comment = null,
    ): Attachment {
        $disk = Storage::disk((string) config('athar.attachments.disk'));
        $storedPath = null;

        try {
            return DB::transaction(function () use ($actor, $request, $file, $kind, $internal, $comment, $disk, &$storedPath) {
                $current = ServiceRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

                if ($current->status->isTerminal()) {
                    throw ValidationException::withMessages(['file' => __('errors.request_closed')]);
                }

                $gate = Gate::forUser($actor);

                if (! $gate->allows('attach', $current)
                    || ($kind->isWorkProduct() && ! $gate->allows('attachWorkProduct', $current))
                    || ($internal && ! $gate->allows('viewInternal', $current))) {
                    throw new AuthorizationException;
                }

                $info = $this->guard->inspect($file);

                $version = 1 + (int) Attachment::query()
                    ->where('request_id', $current->id)
                    ->where('original_name', $info['name'])
                    ->max('version');

                // The path is generated here and never contains anything the uploader typed.
                $storedPath = "attachments/{$current->id}/".Str::uuid().'.'.$info['extension'];
                $disk->putFileAs(dirname($storedPath), $file, basename($storedPath));

                $attachment = Attachment::query()->create([
                    'request_id' => $current->id,
                    'comment_id' => $comment?->id,
                    'uploader_id' => $actor->id,
                    'kind' => $kind,
                    'original_name' => $info['name'],
                    'stored_path' => $storedPath,
                    'mime_type' => $info['mime'],
                    'size' => $file->getSize(),
                    'sha256' => hash_file('sha256', $file->getRealPath()),
                    'version' => $version,
                    'is_internal' => $internal,
                ]);

                $this->log->record(
                    $current,
                    $actor,
                    $kind === AttachmentKind::Deliverable ? ActivityAction::DeliverableUploaded : ActivityAction::FileUploaded,
                    'attachment',
                    null,
                    "{$info['name']} (v{$version})",
                    internal: $internal,
                );

                return $attachment;
            });
        } catch (Throwable $e) {
            // The database rolled back; do not leave an orphan file behind.
            if ($storedPath !== null) {
                $disk->delete($storedPath);
            }

            throw $e;
        }
    }
}
