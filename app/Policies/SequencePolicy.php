<?php

namespace App\Policies;

use App\Models\Sequence;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SequencePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Sequence $sequence): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        // Agents can view parent's sequences
        $ownerId = $user->isAgent() ? $user->parent_user_id : $user->id;
        return $ownerId === $sequence->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Sequence $sequence): bool
    {
        return $user->id === $sequence->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Sequence $sequence): bool
    {
        return $user->id === $sequence->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Sequence $sequence): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Sequence $sequence): bool
    {
        return false;
    }
}
