<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'module',
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * Roles that have this permission
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'permission_role')
            ->withTimestamps();
    }

    // ==================== SCOPES ====================

    /**
     * Scope to get permissions by module
     */
    public function scopeByModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    /**
     * Scope to get permissions grouped by module
     */
    public function scopeGroupedByModule($query)
    {
        return $query->orderBy('module')->orderBy('name');
    }

    // ==================== HELPER METHODS ====================

    /**
     * Get all unique modules
     */
    public static function getModules(): array
    {
        return self::distinct('module')
            ->whereNotNull('module')
            ->pluck('module')
            ->toArray();
    }
}

