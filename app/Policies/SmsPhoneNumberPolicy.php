<?php

namespace App\Policies;

use App\Models\SmsPhoneNumber;
use App\Models\User;

class SmsPhoneNumberPolicy
{
    /**
     * Determine if the user can view the SMS phone number.
     */
    public function view(User $user, SmsPhoneNumber $phoneNumber): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        $ownerId = $user->isAgent() ? $user->parent_user_id : $user->id;
        return $ownerId === $phoneNumber->user_id;
    }

    /**
     * Determine if the user can update the SMS phone number.
     */
    public function update(User $user, SmsPhoneNumber $phoneNumber): bool
    {
        return $user->id === $phoneNumber->user_id;
    }

    /**
     * Determine if the user can delete the SMS phone number.
     */
    public function delete(User $user, SmsPhoneNumber $phoneNumber): bool
    {
        return $user->id === $phoneNumber->user_id;
    }
}
