<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserKycVerification;

class KycPolicy
{
    /**
     * Determine if the user can view KYC verifications.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('kyc.view');
    }

    /**
     * Determine if the user can view their own KYC verification.
     */
    public function view(User $user, UserKycVerification $kyc): bool
    {
        return $user->id === $kyc->user_id || $user->hasPermission('kyc.view');
    }

    /**
     * Determine if the user can submit KYC verification.
     */
    public function create(User $user): bool
    {
        return true; // All authenticated users can submit
    }

    /**
     * Determine if the user can update their KYC verification.
     */
    public function update(User $user, UserKycVerification $kyc): bool
    {
        // Can only update if it's their own and status allows editing
        return $user->id === $kyc->user_id && 
               ($kyc->status === 'rejected' || $kyc->status === 'expired');
    }

    /**
     * Determine if the user can delete KYC verification.
     */
    public function delete(User $user, UserKycVerification $kyc): bool
    {
        return $user->hasPermission('kyc.delete');
    }

    /**
     * Determine if the user can review KYC verifications (admin).
     */
    public function review(User $user): bool
    {
        return $user->hasPermission('kyc.review');
    }

    /**
     * Determine if the user can approve KYC verifications.
     */
    public function approve(User $user, UserKycVerification $kyc): bool
    {
        return $user->hasPermission('kyc.review') && $kyc->status === 'pending';
    }

    /**
     * Determine if the user can reject KYC verifications.
     */
    public function reject(User $user, UserKycVerification $kyc): bool
    {
        return $user->hasPermission('kyc.review') && $kyc->status === 'pending';
    }

    /**
     * Determine if the user can view documents.
     */
    public function viewDocuments(User $user, UserKycVerification $kyc): bool
    {
        return $user->id === $kyc->user_id || $user->hasPermission('kyc.view');
    }
}
