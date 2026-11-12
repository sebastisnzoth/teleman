<?php

namespace App\Policies;

use App\Models\PhoneNumber;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PhoneNumberPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Admin can view all numbers
        // Customers and agents can view available and their own numbers
        return $user->hasPermission('numbers.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PhoneNumber $phoneNumber): bool
    {
        if (!$user->hasPermission('numbers.view')) {
            return false;
        }

        // Admin can view all
        if ($user->isAdmin()) {
            return true;
        }

        // Customers can view available numbers or their assigned numbers
        if ($user->isCustomer()) {
            return $phoneNumber->isAvailable() || $phoneNumber->isOwnedBy($user);
        }

        // Agents can view their parent customer's assigned numbers
        if ($user->isAgent() && $phoneNumber->user_id) {
            return $phoneNumber->user_id === $user->parent_user_id;
        }

        return false;
    }

    /**
     * Determine whether the user can request a number.
     */
    public function request(User $user, PhoneNumber $phoneNumber): bool
    {
        // Only customers can request numbers
        if (!$user->hasPermission('numbers.request') || !$user->isCustomer()) {
            return false;
        }

        // Number must be available
        return $phoneNumber->isAvailable();
    }

    /**
     * Determine whether the user can sync numbers from Twilio.
     */
    public function sync(User $user): bool
    {
        // Only admin can sync from Twilio
        return $user->hasPermission('numbers.sync');
    }

    /**
     * Determine whether the user can release a number.
     */
    public function release(User $user, PhoneNumber $phoneNumber): bool
    {
        // Only admin can release numbers
        return $user->hasPermission('numbers.release');
    }

    /**
     * Determine whether the user can view costs.
     */
    public function viewCosts(User $user): bool
    {
        // Only admin can view costs
        return $user->hasPermission('numbers.costs');
    }
}
