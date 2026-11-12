<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

trait HasRolesAndPermissions
{
    // ==================== RELATIONSHIPS ====================

    /**
     * Roles assigned to this user
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withPivot('created_by_user_id')
            ->withTimestamps();
    }

    /**
     * Get permissions through roles
     */
    public function permissions(): Collection
    {
        return $this->roles()
            ->with('permissions')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->unique('id');
    }

    /**
     * Parent user (for agents)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    /**
     * Child users (agents created by this user)
     */
    public function agents(): HasMany
    {
        return $this->hasMany(User::class, 'parent_user_id');
    }

    // ==================== ROLE CHECKS ====================

    /**
     * Check if user has a specific role
     */
    public function hasRole(string|array $roles): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];

        return $this->roles()
            ->whereIn('slug', $roles)
            ->exists();
    }

    /**
     * Check if user has ANY of the given roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->hasRole($roles);
    }

    /**
     * Check if user has ALL of the given roles
     */
    public function hasAllRoles(array $roles): bool
    {
        foreach ($roles as $role) {
            if (!$this->hasRole($role)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(Role::ADMIN);
    }

    /**
     * Check if user is customer
     */
    public function isCustomer(): bool
    {
        return $this->hasRole(Role::CUSTOMER);
    }

    /**
     * Check if user is agent
     */
    public function isAgent(): bool
    {
        return $this->hasRole(Role::AGENT);
    }

    // ==================== PERMISSION CHECKS ====================

    /**
     * Check if user has a specific permission
     */
    public function hasPermission(string|array $permissions): bool
    {
        $permissions = is_array($permissions) ? $permissions : [$permissions];

        // Admins have all permissions
        if ($this->isAdmin()) {
            return true;
        }

        $userPermissions = $this->permissions()->pluck('slug')->toArray();

        foreach ($permissions as $permission) {
            if (in_array($permission, $userPermissions)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has ANY of the given permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        return $this->hasPermission($permissions);
    }

    /**
     * Check if user has ALL of the given permissions
     */
    public function hasAllPermissions(array $permissions): bool
    {
        // Admins have all permissions
        if ($this->isAdmin()) {
            return true;
        }

        $userPermissions = $this->permissions()->pluck('slug')->toArray();

        foreach ($permissions as $permission) {
            if (!in_array($permission, $userPermissions)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Alias for hasPermission (Laravel Gate compatibility)
     */
    public function can($permission, $arguments = []): bool
    {
        // If arguments are provided (e.g. can('view', $model)), this is a
        // Gate/Policy check — delegate to Laravel's default implementation.
        if (!empty($arguments)) {
            return parent::can($permission, $arguments);
        }

        // No arguments: treat as a custom permission slug check (e.g. can('campaigns.view'))
        if (is_string($permission)) {
            return $this->hasPermission($permission);
        }

        // Fallback to Laravel's default
        return parent::can($permission, $arguments);
    }

    // ==================== ROLE MANAGEMENT ====================

    /**
     * Assign a role to the user
     */
    public function assignRole(string|Role $role, ?int $createdBy = null): self
    {
        if (is_string($role)) {
            $role = Role::where('slug', $role)->firstOrFail();
        }

        $this->roles()->syncWithoutDetaching([
            $role->id => ['created_by_user_id' => $createdBy]
        ]);

        return $this;
    }

    /**
     * Remove a role from the user
     */
    public function removeRole(string|Role $role): self
    {
        if (is_string($role)) {
            $role = Role::where('slug', $role)->firstOrFail();
        }

        $this->roles()->detach($role->id);

        return $this;
    }

    /**
     * Sync roles for the user
     * Accepts either role IDs (integers) or role slugs (strings)
     */
    public function syncRoles(array $roleIdsOrSlugs, ?int $createdBy = null): self
    {
        $syncData = [];
        
        foreach ($roleIdsOrSlugs as $roleIdOrSlug) {
            // If it's a string (slug), convert to ID
            if (is_string($roleIdOrSlug)) {
                $role = Role::where('slug', $roleIdOrSlug)->first();
                if ($role) {
                    $syncData[$role->id] = ['created_by_user_id' => $createdBy];
                }
            } else {
                // It's already an ID
                $syncData[$roleIdOrSlug] = ['created_by_user_id' => $createdBy];
            }
        }

        $this->roles()->sync($syncData);

        return $this;
    }

    // ==================== OWNERSHIP CHECKS ====================

    /**
     * Check if this user owns another user (for agent management)
     */
    public function ownsUser(User $user): bool
    {
        // Admins own all users
        if ($this->isAdmin()) {
            return true;
        }

        // Check direct ownership
        return $user->parent_user_id === $this->id;
    }

    /**
     * Check if user can access data (owns or is admin)
     */
    public function canAccessUserData(User $user): bool
    {
        // Own data
        if ($this->id === $user->id) {
            return true;
        }

        // Admin can access all
        if ($this->isAdmin()) {
            return true;
        }

        // Parent can access agent's data
        if ($this->ownsUser($user)) {
            return true;
        }

        return false;
    }

    /**
     * Get the primary role of the user
     */
    public function primaryRole(): ?Role
    {
        return $this->roles()->orderBy('level')->first();
    }

    /**
     * Get role level (lowest level = highest priority)
     */
    public function getRoleLevel(): int
    {
        $role = $this->primaryRole();
        
        return $role ? $role->level : 999;
    }
}
