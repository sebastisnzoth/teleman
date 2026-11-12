<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThemeStat extends Model
{
    protected $fillable = [
        'number',
        'label',
        'icon',
        'order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    /**
     * Scope for active stats
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for ordered stats
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    /**
     * Get active stats ordered
     */
    public static function getActive()
    {
        return static::active()->ordered()->get();
    }
}
