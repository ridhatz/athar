<?php

namespace App\Policies;

use App\Enums\RoleCode;
use App\Models\Kpi;
use App\Models\User;

/**
 * Who may do what with a KPI sheet.
 *
 * The shape follows the request review loop already in Athar: the person does the work and says
 * so, somebody above them signs it off, and nobody signs off their own. Targets and weights are
 * the administrator's to set — an employee who could edit their own weighting could score
 * themselves.
 */
class KpiPolicy
{
    /** Everyone signed in has a KPI screen; what is listed on it is narrowed by the query. */
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    /** Your own sheet, or anyone's if you are entitled to look over them. */
    public function view(User $user, Kpi $kpi): bool
    {
        return $user->is_active && ($this->isOwner($user, $kpi) || $this->oversees($user, $kpi));
    }

    /** Setting targets and weights is the administrator's job. */
    public function create(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    /** Changing the targets themselves, not the achievements. */
    public function update(User $user, Kpi $kpi): bool
    {
        return $this->create($user);
    }

    /**
     * Report achievements. Only the owner, and only while the sheet is theirs to edit — once it
     * is submitted the numbers are frozen until a reviewer sends it back.
     */
    public function fill(User $user, Kpi $kpi): bool
    {
        return $user->is_active
            && $this->isOwner($user, $kpi)
            && $kpi->status->isEditableByOwner();
    }

    /** Hand the sheet up for review. Same gate as filling it in. */
    public function submit(User $user, Kpi $kpi): bool
    {
        return $this->fill($user, $kpi);
    }

    /**
     * Approve or send back. A supervisor who is not the person being scored: signing off your own
     * KPI would make the whole review meaningless.
     */
    public function review(User $user, Kpi $kpi): bool
    {
        return $user->is_active
            && $kpi->status->awaitsReview()
            && $this->oversees($user, $kpi)
            && ! $this->isOwner($user, $kpi);
    }

    public function isOwner(User $user, Kpi $kpi): bool
    {
        return $kpi->user_id === $user->id;
    }

    /**
     * An administrator, or a team lead who shares a team with the person being scored.
     *
     * Management is deliberately absent: it observes requests, and a KPI sheet is somebody's
     * performance record, so it is not browsing material.
     */
    public function oversees(User $user, Kpi $kpi): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! in_array(RoleCode::TeamLead, $user->roleCodes(), true)) {
            return false;
        }

        $subjectTeams = $kpi->user instanceof User
            ? $kpi->user->teamIds()
            : User::query()->whereKey($kpi->user_id)->first()?->teamIds() ?? [];

        return array_intersect($user->teamIds(), $subjectTeams) !== [];
    }
}
