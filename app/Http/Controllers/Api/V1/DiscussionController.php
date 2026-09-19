<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Requests\AddAttachment;
use App\Actions\Requests\AddComment;
use App\Actions\Requests\DecideReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequests\DecideReviewRequest;
use App\Http\Requests\ServiceRequests\StoreAttachmentRequest;
use App\Http\Requests\ServiceRequests\StoreCommentRequest;
use App\Http\Resources\AttachmentResource;
use App\Http\Resources\CommentResource;
use App\Http\Resources\RequestDetailResource;
use App\Http\Resources\ReviewResource;
use App\Models\ServiceRequest;
use App\Queries\Discussion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Comments, files and reviews of a request. Thin: the Actions hold the rules. */
class DiscussionController extends Controller
{
    public function comments(Request $request, ServiceRequest $serviceRequest, Discussion $discussion): AnonymousResourceCollection
    {
        return CommentResource::collection($discussion->comments($request->user(), $serviceRequest));
    }

    public function storeComment(StoreCommentRequest $request, ServiceRequest $serviceRequest, AddComment $add): JsonResponse
    {
        $comment = $add->handle(
            $request->user(),
            $serviceRequest,
            $request->validated('body'),
            $request->boolean('is_internal'),
            $request->file('file'),
            $request->kind(),
        );

        return CommentResource::make($comment->load('author:id,name', 'attachments.uploader:id,name'))
            ->response()->setStatusCode(201);
    }

    public function attachments(Request $request, ServiceRequest $serviceRequest, Discussion $discussion): AnonymousResourceCollection
    {
        return AttachmentResource::collection($discussion->attachments($request->user(), $serviceRequest));
    }

    public function storeAttachment(StoreAttachmentRequest $request, ServiceRequest $serviceRequest, AddAttachment $add): JsonResponse
    {
        $attachment = $add->handle(
            $request->user(),
            $serviceRequest,
            $request->file('file'),
            $request->kind(),
            $request->boolean('is_internal'),
        );

        return AttachmentResource::make($attachment->load('uploader:id,name'))->response()->setStatusCode(201);
    }

    public function reviews(ServiceRequest $serviceRequest): AnonymousResourceCollection
    {
        return ReviewResource::collection(
            $serviceRequest->reviews()->with(['requester:id,name', 'reviewer:id,name'])->orderBy('round')->get(),
        );
    }

    public function decide(DecideReviewRequest $request, ServiceRequest $serviceRequest, DecideReview $decide): RequestDetailResource
    {
        $decided = $decide->handle($request->user(), $serviceRequest, $request->decision(), $request->validated('note'));

        return RequestDetailResource::make($decided->load(['category', 'department', 'requester', 'assignedTeam', 'assignedUser']));
    }
}
