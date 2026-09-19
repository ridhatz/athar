<?php

namespace App\Workflow;

use App\Enums\RequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * The state machine. Pure rules: no persistence, no HTTP.
 *
 * The transition table itself lives on RequestStatus. This class adds the parts
 * that depend on a concrete request (BLOCKED resumes to where it came from) and
 * answers "what may this person do next?" so the UI never has to guess.
 */
final class RequestWorkflow
{
    /** Handled by the assignment use-case, not the generic status endpoint. */
    public const VIA_ASSIGNMENT = 'assignment';

    /** Review decisions (approve / request revision), added with the review system. */
    public const VIA_REVIEW = 'review';

    /**
     * Statuses this request may move to, per the transition table.
     *
     * @return array<int, RequestStatus>
     */
    public static function targets(ServiceRequest $request): array
    {
        if ($request->status === RequestStatus::Blocked) {
            return array_values(array_filter([$request->blocked_from_status, RequestStatus::Cancelled]));
        }

        return $request->status->transitions();
    }

    public static function canMove(ServiceRequest $request, RequestStatus $to): bool
    {
        return in_array($to, self::targets($request), true);
    }

    /**
     * Some valid moves belong to a dedicated use-case rather than the generic status
     * endpoint. Returns which one, or null when the generic endpoint handles it.
     */
    public static function reservedFor(ServiceRequest $request, RequestStatus $to): ?string
    {
        $from = $request->status;

        if ($from === RequestStatus::Triaged && $to === RequestStatus::Assigned) {
            return self::VIA_ASSIGNMENT;
        }

        if ($from === RequestStatus::Review && in_array($to, [RequestStatus::Completed, RequestStatus::InProgress], true)) {
            return self::VIA_REVIEW;
        }

        return null;
    }

    /**
     * Moves this user may make right now through the generic status endpoint.
     * Sent to the UI so it can render buttons without duplicating any rule.
     *
     * @return array<int, array{status: string, requires_note: bool}>
     */
    public static function allowedFor(User $user, ServiceRequest $request): array
    {
        if ($request->status->isTerminal()) {
            return [];
        }

        $allowed = [];

        foreach (self::targets($request) as $to) {
            if (self::reservedFor($request, $to) !== null) {
                continue;
            }

            if ($to->requiresPic() && $request->assigned_user_id === null) {
                continue;
            }

            if (! Gate::forUser($user)->allows('transition', [$request, $to])) {
                continue;
            }

            $allowed[] = ['status' => $to->value, 'requires_note' => $to->requiresNote()];
        }

        return $allowed;
    }
}
