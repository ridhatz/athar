<?php

namespace App\Policies;

use App\Models\Assessment;
use App\Models\User;

/**
 * Who may do what with the Communication Behavior Assessment.
 *
 * Simpler than KPI's review loop on purpose: this is a self-report instrument, not a workflow
 * with sign-off — anyone with an account may take it and see their own result, and an
 * administrator may look at everyone's, full stop.
 */
class AssessmentPolicy
{
    /** Everyone with an active account has the screen; what else it shows is the controller's call. */
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    /** Your own result, or any of them if you administer the roster. */
    public function view(User $user, Assessment $assessment): bool
    {
        return $user->is_active && ($this->isOwner($user, $assessment) || $user->isAdmin());
    }

    /** Anyone may take (or retake) their own assessment. */
    public function create(User $user): bool
    {
        return $user->is_active;
    }

    /** Whether the page's admin roster section is shown at all. */
    public function viewAll(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function isOwner(User $user, Assessment $assessment): bool
    {
        return $assessment->user_id === $user->id;
    }
}
