<?php

namespace App\Http\Controllers;

use App\Actions\Requests\AddAttachment;
use App\Actions\Requests\AddComment;
use App\Actions\Requests\DecideReview;
use App\Http\Requests\ServiceRequests\DecideReviewRequest;
use App\Http\Requests\ServiceRequests\StoreAttachmentRequest;
use App\Http\Requests\ServiceRequests\StoreCommentRequest;
use App\Models\ServiceRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/** Web form endpoints for the discussion, files and review decisions. The same Actions as the API. */
class RequestDiscussionController extends Controller
{
    public function comment(StoreCommentRequest $request, ServiceRequest $serviceRequest, AddComment $add): RedirectResponse
    {
        $add->handle(
            $request->user(),
            $serviceRequest,
            $request->validated('body'),
            $request->boolean('is_internal'),
            $request->file('file'),
            $request->kind(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('discussion.flash.comment')]);

        return back();
    }

    public function attach(StoreAttachmentRequest $request, ServiceRequest $serviceRequest, AddAttachment $add): RedirectResponse
    {
        $add->handle(
            $request->user(),
            $serviceRequest,
            $request->file('file'),
            $request->kind(),
            $request->boolean('is_internal'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('discussion.flash.file')]);

        return back();
    }

    public function review(DecideReviewRequest $request, ServiceRequest $serviceRequest, DecideReview $decide): RedirectResponse
    {
        $decide->handle($request->user(), $serviceRequest, $request->decision(), $request->validated('note'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('discussion.flash.review')]);

        return back();
    }
}
