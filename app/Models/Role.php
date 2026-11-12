<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    // Role levels
    public const LEVEL_ADMIN = 1;
    public const LEVEL_CUSTOMER = 2;
    public const LEVEL_AGENT = 3;

    // Role slugs
    public const ADMIN = 'admin';
    public const CUSTOMER = 'customer';
    public const AGENT = 'agent';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'level',
        'is_system',
    ];

    protected $casts = [
        'level' => 'integer',
        'is_system' => 'boolean',
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * Users that have this role
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')
            ->withPivot('created_by_user_id')
            ->withTimestamps();
    }

    /**
     * Permissions associated with this role
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role')
            ->withTimestamps();
    }

    // ==================== HELPER METHODS ====================

    /**
     * Check if this is the admin role
     */
    public function isAdmin(): bool
    {
        return $this->slug === self::ADMIN || $this->level === self::LEVEL_ADMIN;
    }

    /**
     * Check if this is the customer role
     */
    public function isCustomer(): bool
    {
        return $this->slug === self::CUSTOMER || $this->level === self::LEVEL_CUSTOMER;
    }

    /**
     * Check if this is the agent role
     */
    public function isAgent(): bool
    {
        return $this->slug === self::AGENT || $this->level === self::LEVEL_AGENT;
    }

    /**
     * Check if this role has a specific permission
     */
    public function hasPermission(string $permissionSlug): bool
    {
        return $this->permissions()->where('slug', $permissionSlug)->exists();
    }

    /**
     * Give permission to this role
     */
    public function givePermission(Permission|string $permission): self
    {
        if (is_string($permission)) {
            $permission = Permission::where('slug', $permission)->firstOrFail();
        }

        $this->permissions()->syncWithoutDetaching([$permission->id]);

        return $this;
    }

    /**
     * Revoke permission from this role
     */
    public function revokePermission(Permission|string $permission): self
    {
        if (is_string($permission)) {
            $permission = Permission::where('slug', $permission)->firstOrFail();
        }

        $this->permissions()->detach($permission->id);

        return $this;
    }

    /**
     * Sync permissions for this role
     */
    public function syncPermissions(array $permissionIds): self
    {
        $this->permissions()->sync($permissionIds);

        return $this;
    }

    // ==================== SCOPES ====================

    /**
     * Scope to get only system roles
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Scope to get roles by level
     */
    public function scopeByLevel($query, int $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope to get roles accessible by a user
     * Admin can see all, Customer can see Customer and Agent, Agent can't create roles
     */
    public function scopeAccessibleBy($query, User $user)
    {
        $role = $user->roles()->first();
        
        if (!$role) {
            return $query->whereNull('id'); // No access
        }

        if ($role->isAdmin()) {
            return $query; // Admin sees all
        }

        if ($role->isCustomer()) {
            return $query->whereIn('slug', [self::CUSTOMER, self::AGENT]); // Customer sees Customer and Agent
        }

        return $query->whereNull('id'); // Agents can't see roles
    }
}

