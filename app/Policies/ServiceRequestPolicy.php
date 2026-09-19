<?php

namespace App\Policies;

use App\Enums\RequestStatus;
use App\Enums\RoleCode;
use App\Models\Attachment;
use App\Models\ServiceRequest;
use App\Models\User;

/**
 * Every "who may do what" decision about requests, in one file.
 * Controllers and actions ask the Gate; nobody checks roles inline.
 *
 * Vocabulary
 *  - owner:   the person who created the request.
 *  - PIC:     the individual currently assigned to do the work.
 *  - manager: an ADMIN, or a TEAM_LEAD of the request's team (any team lead while
 *             the request has no team yet, so the triage queue is workable).
 */
class ServiceRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    /** Visibility is defined once, as a query scope; this simply asks it. */
    public function view(User $user, ServiceRequest $request): bool
    {
        return $user->is_active
            && ServiceRequest::query()->visibleTo($user)->whereKey($request->getKey())->exists();
    }

    /** Everyone who can be on the requesting side. MANAGEMENT alone is observe-only. */
    public function create(User $user): bool
    {
        return $user->is_active
            && $user->hasAnyRole(RoleCode::Requester, RoleCode::Staff, RoleCode::TeamLead, RoleCode::Admin);
    }

    /** Title, description, category, deadline. */
    public function editDetails(User $user, ServiceRequest $request): bool
    {
        if (! $user->is_active || $request->status->isTerminal()) {
            return false;
        }

        return $this->isManager($user, $request)
            || ($this->isOwner($user, $request) && $request->status === RequestStatus::New);
    }

    /** Requester until triage; ADMIN may override at any time while the request is open. */
    public function editPriority(User $user, ServiceRequest $request): bool
    {
        if (! $user->is_active || $request->status->isTerminal()) {
            return false;
        }

        return $user->isAdmin()
            || ($this->isOwner($user, $request) && $request->status === RequestStatus::New);
    }

    public function assign(User $user, ServiceRequest $request): bool
    {
        return $user->is_active
            && $request->status->isOpen()
            && $this->isManager($user, $request);
    }

    /**
     * May this user move the request into $to?
     *
     * Only about WHO. Whether the move exists at all (NEW → TRIAGED yes, IN_PROGRESS → TRIAGED no)
     * is the workflow's decision, so an impossible move is reported as invalid (422), not forbidden (403).
     */
    public function transition(User $user, ServiceRequest $request, RequestStatus $to): bool
    {
        if (! $user->is_active || $request->status->isTerminal()) {
            return false;
        }

        return match ($to) {
            RequestStatus::Cancelled => $this->isManager($user, $request)
                || ($this->isOwner($user, $request) && $this->ownerMayCancel($request)),
            RequestStatus::Triaged => $this->isManager($user, $request),
            default => $this->isManager($user, $request) || $this->isPic($user, $request),
        };
    }

    /** Internal notes and internal activity are for the working side, never the requester. */
    public function viewInternal(User $user, ServiceRequest $request): bool
    {
        if ($this->isOwner($user, $request)) {
            return false;
        }

        return $user->hasAnyRole(RoleCode::Admin, RoleCode::Management, RoleCode::TeamLead, RoleCode::Staff);
    }

    /** Join the discussion: the requester and the working side, while the request is open. MANAGEMENT only observes. */
    public function comment(User $user, ServiceRequest $request): bool
    {
        return $user->is_active
            && $request->status->isOpen()
            && ($this->isOwner($user, $request) || $user->hasAnyRole(RoleCode::Admin, RoleCode::TeamLead, RoleCode::Staff));
    }

    /** An internal note is only visible to, and so only writable by, the working side. */
    public function commentInternal(User $user, ServiceRequest $request): bool
    {
        return $this->comment($user, $request) && $this->viewInternal($user, $request);
    }

    /** Add files: the requester, the PIC, or a manager, while the request is open. */
    public function attach(User $user, ServiceRequest $request): bool
    {
        return $user->is_active
            && $request->status->isOpen()
            && ($this->isOwner($user, $request) || $this->isManager($user, $request) || $this->isPic($user, $request));
    }

    /** Drafts, revisions and final deliverables come from the people doing the work. */
    public function attachWorkProduct(User $user, ServiceRequest $request): bool
    {
        return $this->attach($user, $request) && ($this->isManager($user, $request) || $this->isPic($user, $request));
    }

    /** A file is readable by anyone who can see the request, except internal files, which stay on the working side. */
    public function downloadAttachment(User $user, ServiceRequest $request, Attachment $attachment): bool
    {
        return $this->view($user, $request)
            && $attachment->request_id === $request->id
            && (! $attachment->is_internal || $this->viewInternal($user, $request));
    }

    /**
     * Approve or send back the work in review. The reviewer is a manager who is NOT the person
     * who did the work: nobody signs off their own output.
     */
    public function reviewDecision(User $user, ServiceRequest $request): bool
    {
        return $user->is_active
            && $request->status === RequestStatus::Review
            && $this->isManager($user, $request)
            && ! $this->isPic($user, $request);
    }

    public function isOwner(User $user, ServiceRequest $request): bool
    {
        return $request->requester_id === $user->id;
    }

    public function isPic(User $user, ServiceRequest $request): bool
    {
        return $request->assigned_user_id !== null && $request->assigned_user_id === $user->id;
    }

    public function isManager(User $user, ServiceRequest $request): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->hasRole(RoleCode::TeamLead)
            && ($request->assigned_team_id === null || $user->isInTeam($request->assigned_team_id));
    }

    /** A requester may withdraw until work has actually started. */
    private function ownerMayCancel(ServiceRequest $request): bool
    {
        return in_array($request->status, [RequestStatus::New, RequestStatus::Triaged, RequestStatus::Assigned], true);
    }
}
