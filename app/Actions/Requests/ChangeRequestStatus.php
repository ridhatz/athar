<?php

namespace App\Actions\Requests;

use App\Enums\ActivityAction;
use App\Enums\RequestStatus;
use App\Enums\ReviewDecision;
use App\Events\RequestNotable;
use App\Models\Review;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Workflow\RequestWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Moves a request along the workflow. This is the single place a status changes
 * (AssignRequest reuses the same primitive for the automatic triage/assign steps).
 */
final class ChangeRequestStatus
{
    public function __construct(private readonly ActivityLogger $log) {}

    public function handle(User $actor, ServiceRequest $request, RequestStatus $to, ?string $note = null): ServiceRequest
    {
        return DB::transaction(function () use ($actor, $request, $to, $note) {
            // Lock the row so two people acting at once are serialised, and the second sees the first's result.
            $current = ServiceRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $from = $current->status;

            if ($from->isTerminal()) {
                throw ValidationException::withMessages(['to_status' => __('errors.request_closed')]);
            }

            Gate::forUser($actor)->authorize('transition', [$current, $to]);

            if (! RequestWorkflow::canMove($current, $to)) {
                throw ValidationException::withMessages([
                    'to_status' => __('errors.invalid_transition', ['from' => $from->value, 'to' => $to->value]),
                ]);
            }

            match (RequestWorkflow::reservedFor($current, $to)) {
                RequestWorkflow::VIA_ASSIGNMENT => throw ValidationException::withMessages(['to_status' => __('errors.reserved_assignment')]),
                RequestWorkflow::VIA_REVIEW => throw ValidationException::withMessages(['to_status' => __('errors.reserved_review')]),
                default => null,
            };

            if ($to->requiresPic() && $current->assigned_user_id === null) {
                throw ValidationException::withMessages(['to_status' => __('errors.pic_required')]);
            }

            if ($to->requiresNote() && ($note === null || trim($note) === '')) {
                throw ValidationException::withMessages(['note' => __('errors.note_required')]);
            }

            $this->apply($actor, $current, $to, $note);

            return $current;
        });
    }

    /**
     * Apply an already-validated move and record it. Also used by AssignRequest
     * for the automatic NEW → TRIAGED → ASSIGNED steps.
     */
    public function apply(?User $actor, ServiceRequest $request, RequestStatus $to, ?string $note = null): void
    {
        $from = $request->status;

        $request->status = $to;

        // Remember where BLOCKED came from so it can resume there; forget it once unblocked.
        $request->blocked_from_status = $to === RequestStatus::Blocked ? $from : null;

        if ($to === RequestStatus::Completed) {
            $request->completed_at = now();
        }

        if ($to === RequestStatus::Cancelled) {
            $request->cancelled_at = now();
        }

        $request->save();

        $this->log->record($request, $actor, ActivityAction::StatusChanged, 'status', $from->value, $to->value, $note);

        // Tell the right people, but only once the change is really saved.
        DB::afterCommit(fn () => event(new RequestNotable(RequestNotable::STATUS_CHANGED, $request, $actor, ['from' => $from->value, 'to' => $to->value])));

        // Sending work to review opens a new review round (resuming a blocked review does not).
        if ($to === RequestStatus::Review && $from === RequestStatus::InProgress) {
            $round = 1 + (int) Review::query()->where('request_id', $request->id)->max('round');

            Review::query()->create([
                'request_id' => $request->id,
                'round' => $round,
                'requested_by' => $actor?->id ?? $request->assigned_user_id,
                'requested_at' => now(),
                'decision' => ReviewDecision::Pending,
            ]);

            $this->log->record($request, $actor, ActivityAction::ReviewRequested, 'review', null, (string) $round);
        }
    }
}
