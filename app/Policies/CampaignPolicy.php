<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CampaignPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Admin, Customer, and Agent can view campaigns
        return $user->hasPermission('campaigns.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Campaign $campaign): bool
    {
        // Check permission first
        if (!$user->hasPermission('campaigns.view')) {
            return false;
        }

        // Admin can view all
        if ($user->isAdmin()) {
            return true;
        }

        // Customers can view their own campaigns
        if ($user->isCustomer() && $campaign->user_id === $user->id) {
            return true;
        }

        // Agents can view campaigns explicitly assigned to them
        if ($user->isAgent() && $campaign->assigned_agent_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only Admin and Customer can create campaigns
        return $user->hasPermission('campaigns.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Campaign $campaign): bool
    {
        // Check permission first
        if (!$user->hasPermission('campaigns.edit')) {
            return false;
        }

        // Admin can edit all
        if ($user->isAdmin()) {
            return true;
        }

        // Customers can edit their own campaigns
        if ($user->isCustomer() && $campaign->user_id === $user->id) {
            return true;
        }

        // Agents cannot edit campaigns
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Campaign $campaign): bool
    {
        // Check permission first
        if (!$user->hasPermission('campaigns.delete')) {
            return false;
        }

        // Admin can delete all
        if ($user->isAdmin()) {
            return true;
        }

        // Customers can delete their own campaigns
        if ($user->isCustomer() && $campaign->user_id === $user->id) {
            return true;
        }

        // Agents cannot delete campaigns
        return false;
    }

    /**
     * Determine whether the user can execute (start/stop) the campaign.
     */
    public function execute(User $user, Campaign $campaign): bool
    {
        // Check permission first
        if (!$user->hasPermission('campaigns.execute')) {
            return false;
        }

        // Admin can execute all
        if ($user->isAdmin()) {
            return true;
        }

        // Customers can execute their own campaigns
        if ($user->isCustomer() && $campaign->user_id === $user->id) {
            return true;
        }

        // Agents can execute campaigns explicitly assigned to them
        if ($user->isAgent() && $campaign->assigned_agent_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view campaign analytics.
     */
    public function viewAnalytics(User $user, Campaign $campaign): bool
    {
        // Check permission first
        if (!$user->hasPermission('campaigns.analytics')) {
            return false;
        }

        // Admin can view all analytics
        if ($user->isAdmin()) {
            return true;
        }

        // Customers can view their own campaign analytics
        if ($user->isCustomer() && $campaign->user_id === $user->id) {
            return true;
        }

        // Agents can view analytics for campaigns assigned to them
        if ($user->isAgent() && $campaign->assigned_agent_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Campaign $campaign): bool
    {
        return $this->delete($user, $campaign);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Campaign $campaign): bool
    {
        // Only admins can force delete
        return $user->isAdmin();
    }
}

