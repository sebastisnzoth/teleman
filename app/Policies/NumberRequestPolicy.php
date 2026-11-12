<?php

namespace App\Policies;

use App\Models\NumberRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class NumberRequestPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Admin can view all requests
        // Customers can view their own requests
        return $user->hasPermission('numbers.approve') || $user->hasPermission('numbers.request');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, NumberRequest $numberRequest): bool
    {
        // Admin can view all
        if ($user->isAdmin()) {
            return true;
        }

        // Customers can view their own requests
        if ($user->isCustomer()) {
            return $numberRequest->customer_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only customers can create requests
        return $user->hasPermission('numbers.request') && $user->isCustomer();
    }

    /**
     * Determine whether the user can approve/reject requests.
     */
    public function process(User $user, NumberRequest $numberRequest): bool
    {
        // Only admin can approve/reject
        if (!$user->hasPermission('numbers.approve')) {
            return false;
        }

        // Can only process pending requests
        return $numberRequest->isPending();
    }

    /**
     * Determine whether the user can cancel a request.
     */
    public function cancel(User $user, NumberRequest $numberRequest): bool
    {
        // Only the customer who created the request can cancel
        if (!$user->isCustomer() || $numberRequest->customer_id !== $user->id) {
            return false;
        }

        // Can only cancel pending requests
        return $numberRequest->canBeCancelled();
    }
}
