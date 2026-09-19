<?php

namespace App\Actions\Requests;

use App\Enums\ActivityAction;
use App\Enums\RequestStatus;
use App\Enums\ReviewDecision;
use App\Models\Review;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The reviewer's decision on work in review.
 *
 *   approve  → the review round is closed APPROVED and the request COMPLETED.
 *   revision → the round is closed REVISION_REQUESTED (a reason is required) and the request goes back
 *              to IN_PROGRESS. When the PIC submits again a NEW round starts, so every round stays on record.
 */
final class DecideReview
{
    public function __construct(
        private readonly ActivityLogger $log,
        private readonly ChangeRequestStatus $status,
    ) {}

    public function handle(User $actor, ServiceRequest $request, ReviewDecision $decision, ?string $note = null): ServiceRequest
    {
        if ($decision === ReviewDecision::Pending) {
            throw ValidationException::withMessages(['decision' => __('errors.review_decision_invalid')]);
        }

        return DB::transaction(function () use ($actor, $request, $decision, $note) {
            $current = ServiceRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($current->status !== RequestStatus::Review) {
                throw ValidationException::withMessages(['decision' => __('errors.not_in_review')]);
            }

            Gate::forUser($actor)->authorize('reviewDecision', $current);

            $note = $note !== null ? trim($note) : null;

            if ($decision === ReviewDecision::RevisionRequested && ($note === null || $note === '')) {
                throw ValidationException::withMessages(['note' => __('errors.revision_note_required')]);
            }

            $review = Review::query()
                ->where('request_id', $current->id)
                ->where('decision', ReviewDecision::Pending->value)
                ->latest('round')
                ->first();

            if ($review === null) {
                throw ValidationException::withMessages(['decision' => __('errors.not_in_review')]);
            }

            $review->update([
                'reviewer_id' => $actor->id,
                'decision' => $decision,
                'decision_note' => $note ?: null,
                'decided_at' => now(),
            ]);

            $approved = $decision === ReviewDecision::Approved;

            $this->log->record(
                $current,
                $actor,
                $approved ? ActivityAction::ReviewApproved : ActivityAction::RevisionRequested,
                'review',
                null,
                (string) $review->round,
                $note,
            );

            $this->status->apply($actor, $current, $approved ? RequestStatus::Completed : RequestStatus::InProgress);

            return $current;
        });
    }
}
