<?php

namespace App\Actions\Requests;

use App\Enums\ActivityAction;
use App\Enums\AttachmentKind;
use App\Models\Comment;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Post to a request's discussion, publicly or as an internal note, optionally with one file.
 * The activity log records THAT a comment was made (visible to the requester only if it is public);
 * the words themselves live in the comment.
 */
final class AddComment
{
    public function __construct(
        private readonly ActivityLogger $log,
        private readonly AddAttachment $attach,
    ) {}

    public function handle(
        User $actor,
        ServiceRequest $request,
        string $body,
        bool $internal = false,
        ?UploadedFile $file = null,
        ?AttachmentKind $kind = null,
    ): Comment {
        return DB::transaction(function () use ($actor, $request, $body, $internal, $file, $kind) {
            $current = ServiceRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($current->status->isTerminal()) {
                throw ValidationException::withMessages(['body' => __('errors.request_closed')]);
            }

            $gate = Gate::forUser($actor);

            if (! $gate->allows('comment', $current) || ($internal && ! $gate->allows('commentInternal', $current))) {
                throw new AuthorizationException;
            }

            $body = trim($body);

            if ($body === '') {
                throw ValidationException::withMessages(['body' => __('errors.comment_empty')]);
            }

            $comment = Comment::query()->create([
                'request_id' => $current->id,
                'author_id' => $actor->id,
                'body' => $body,
                'is_internal' => $internal,
            ]);

            if ($file !== null) {
                $this->attach->handle($actor, $current, $file, $kind ?? AttachmentKind::Other, $internal, $comment);
            }

            $this->log->record($current, $actor, ActivityAction::CommentAdded, 'comment', null, null, internal: $internal);

            return $comment;
        });
    }
}
