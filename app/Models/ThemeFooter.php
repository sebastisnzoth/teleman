<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThemeFooter extends Model
{
    protected $table = 'theme_footer';

    protected $fillable = [
        'cta_badge_text',
        'cta_badge_icon',
        'cta_headline',
        'cta_description',
        'cta_primary_text',
        'cta_primary_icon',
        'cta_secondary_text',
        'trust_indicators',
        'background_gradient',
        'is_active',
    ];

    protected $casts = [
        'trust_indicators' => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function getActive()
    {
        return static::active()->first();
    }
}
