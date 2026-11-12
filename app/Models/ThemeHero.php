<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThemeHero extends Model
{
    protected $table = 'theme_hero';

    protected $fillable = [
        'badge_text',
        'badge_icon_left',
        'badge_icon_right',
        'typewriter_text',
        'subtitle',
        'subtitle_emoji',
        'primary_cta_text',
        'primary_cta_icon',
        'secondary_cta_text',
        'secondary_cta_icon',
        'background_gradient',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Scope for active hero
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the active hero section
     */
    public static function getActive()
    {
        return static::active()->first();
    }
}
