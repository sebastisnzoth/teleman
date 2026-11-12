<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ContactPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('contacts.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Contact $contact): bool
    {
        // Check permission first
        if (!$user->hasPermission('contacts.view')) {
            return false;
        }

        // Admin can view all
        if ($user->isAdmin()) {
            return true;
        }

        // Customers can view their own contacts
        if ($user->isCustomer() && $contact->user_id === $user->id) {
            return true;
        }

        // Agents can view their parent's contacts
        if ($user->isAgent() && $user->parent_user_id === $contact->user_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only Admin and Customer can create contacts
        return $user->hasPermission('contacts.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Contact $contact): bool
    {
        // Check permission first
        if (!$user->hasPermission('contacts.edit')) {
            return false;
        }

        // Admin can edit all
        if ($user->isAdmin()) {
            return true;
        }

        // Customers can edit their own contacts
        if ($user->isCustomer() && $contact->user_id === $user->id) {
            return true;
        }

        // Agents cannot edit contacts
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Contact $contact): bool
    {
        // Check permission first
        if (!$user->hasPermission('contacts.delete')) {
            return false;
        }

        // Admin can delete all
        if ($user->isAdmin()) {
            return true;
        }

        // Customers can delete their own contacts
        if ($user->isCustomer() && $contact->user_id === $user->id) {
            return true;
        }

        // Agents cannot delete contacts
        return false;
    }

    /**
     * Determine whether the user can import contacts.
     */
    public function import(User $user): bool
    {
        return $user->hasPermission('contacts.import');
    }

    /**
     * Determine whether the user can export contacts.
     */
    public function export(User $user): bool
    {
        return $user->hasPermission('contacts.export');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Contact $contact): bool
    {
        return $this->delete($user, $contact);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Contact $contact): bool
    {
        // Only admins can force delete
        return $user->isAdmin();
    }
}

