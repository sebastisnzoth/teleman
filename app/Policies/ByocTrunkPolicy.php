<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ByocTrunk;

class ByocTrunkPolicy
{
    /**
     * Determine if the user can view any BYOC trunks
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the BYOC trunk
     */
    public function view(User $user, ByocTrunk $trunk): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        $ownerId = $user->isAgent() ? $user->parent_user_id : $user->id;
        return $ownerId === $trunk->user_id;
    }

    /**
     * Determine if the user can create BYOC trunks
     */
    public function create(User $user): bool
    {
        return !empty($user->twilio_account_sid) && !empty($user->twilio_auth_token);
    }

    /**
     * Determine if the user can update the BYOC trunk
     */
    public function update(User $user, ByocTrunk $trunk): bool
    {
        return $user->id === $trunk->user_id;
    }

    /**
     * Determine if the user can delete the BYOC trunk
     */
    public function delete(User $user, ByocTrunk $trunk): bool
    {
        return $user->id === $trunk->user_id;
    }
}
