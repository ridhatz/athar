<?php

namespace App\Policies;

use App\Models\User;

/** Managing people is for ADMIN only. Everyone else, including management, gets a 403. */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, User $subject): bool
    {
        return $this->viewAny($user);
    }
}
