<?php

namespace App\Policies;

use App\Models\User;

/**
 * Reference data (teams, categories, departments) is shaped by ADMIN only.
 * Everyone else, including management, gets a 403 — same stance as UserPolicy.
 */
abstract class ReferenceDataPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user): bool
    {
        return $this->viewAny($user);
    }
}
