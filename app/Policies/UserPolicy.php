<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Admin can view all users
        // Customers can view their agents
        return $user->hasPermission('users.view') || $user->hasPermission('agents.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // Admin can view all
        if ($user->isAdmin()) {
            return true;
        }

        // Can view own profile
        if ($user->id === $model->id) {
            return true;
        }

        // Customers can view their agents
        if ($user->isCustomer() && $model->parent_user_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models (users/agents).
     */
    public function create(User $user): bool
    {
        // Admin can create any type of user
        if ($user->hasPermission('users.create')) {
            return true;
        }

        // Customers can create agents
        if ($user->hasPermission('agents.create')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // Admins cannot modify other admin accounts (mutual admin protection)
        if ($user->isAdmin() && $model->isAdmin() && $user->id !== $model->id) {
            return false;
        }

        // Admin can update all other users
        if ($user->isAdmin() && $user->hasPermission('users.edit')) {
            return true;
        }

        // Can update own profile (basic info only)
        if ($user->id === $model->id) {
            return true;
        }

        // Customers can update their agents
        if ($user->isCustomer() && $user->hasPermission('agents.edit') && $model->parent_user_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Cannot delete own account
        if ($user->id === $model->id) {
            return false;
        }

        // Admins cannot delete other admin accounts
        if ($user->isAdmin() && $model->isAdmin()) {
            return false;
        }

        // Admin can delete any non-admin user
        if ($user->isAdmin() && $user->hasPermission('users.delete')) {
            return true;
        }

        // Customers can delete their agents
        if ($user->isCustomer() && $user->hasPermission('agents.delete') && $model->parent_user_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can assign roles.
     */
    public function assignRole(User $user, User $model): bool
    {
        // Only admin can assign roles
        return $user->isAdmin() && $user->hasPermission('users.roles');
    }

    /**
     * Determine whether the user can assign permissions to agents.
     */
    public function assignPermissions(User $user, User $model): bool
    {
        // Admin can assign permissions to anyone
        if ($user->isAdmin()) {
            return true;
        }

        // Customers can assign permissions to their agents
        if ($user->isCustomer() && $user->hasPermission('agents.permissions') && $model->parent_user_id === $user->id && $model->isAgent()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return $this->delete($user, $model);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        // Only admins can force delete
        return $user->isAdmin();
    }
}
