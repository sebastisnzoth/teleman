<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThemePricing extends Model
{
    protected $table = 'theme_pricing';

    protected $fillable = [
        'credits',
        'price',
        'per_credit',
        'popular',
        'icon',
        'savings',
        'order',
        'is_active',
    ];

    protected $casts = [
        'popular' => 'boolean',
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    public static function getActive()
    {
        return static::active()->ordered()->get();
    }
}
