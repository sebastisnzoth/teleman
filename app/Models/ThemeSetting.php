<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ThemeSetting extends Model
{
    protected $fillable = [
        'site_name',
        'site_tagline',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'logo_path',
        'logo_dark_path',
        'logo_display_landing',
        'logo_display_dashboard',
        'logo_display_auth',
        'logo_size_landing',
        'logo_size_dashboard',
        'logo_size_auth',
        'favicon_path',
        'og_image_path',
        'twitter_card',
        'twitter_site',
        'google_analytics_id',
        'google_tag_manager_id',
        'facebook_pixel_id',
        'custom_head_scripts',
        'custom_body_scripts',
        'primary_color',
        'secondary_color',
        'can_register',
        'trust_badges',
        'social_links',
        'legal_links',
        'copyright_text',
    ];

    protected $casts = [
        'can_register' => 'boolean',
        'trust_badges' => 'array',
        'social_links' => 'array',
        'legal_links' => 'array',
    ];

    protected $appends = ['logo_url', 'logo_dark_url', 'favicon_url', 'og_image_url'];

    /**
     * Get the logo URL accessor
     */
    public function getLogoUrlAttribute()
    {
        if (!$this->logo_path) {
            return null;
        }

        // If it's already a full URL, return it
        if (str_starts_with($this->logo_path, 'http') || str_starts_with($this->logo_path, '/storage/')) {
            return $this->logo_path;
        }

        // Otherwise, generate the URL from the storage path
        return Storage::disk('public')->url($this->logo_path);
    }

    /**
     * Get the dark-mode logo URL accessor
     */
    public function getLogoDarkUrlAttribute()
    {
        if (!$this->logo_dark_path) {
            return null;
        }

        if (str_starts_with($this->logo_dark_path, 'http') || str_starts_with($this->logo_dark_path, '/storage/')) {
            return $this->logo_dark_path;
        }

        return Storage::disk('public')->url($this->logo_dark_path);
    }

    /**
     * Get the favicon URL accessor
     */
    public function getFaviconUrlAttribute()
    {
        if (!$this->favicon_path) {
            return asset('favicon.ico');
        }

        // If it's already a full URL, return it
        if (str_starts_with($this->favicon_path, 'http') || str_starts_with($this->favicon_path, '/storage/')) {
            return $this->favicon_path;
        }

        // Otherwise, generate the URL from the storage path
        return Storage::disk('public')->url($this->favicon_path);
    }

    /**
     * Get the OG image URL accessor
     */
    public function getOgImageUrlAttribute()
    {
        if (!$this->og_image_path) {
            return null;
        }

        // If it's already a full URL, return it
        if (str_starts_with($this->og_image_path, 'http') || str_starts_with($this->og_image_path, '/storage/')) {
            return $this->og_image_path;
        }

        // Otherwise, generate the URL from the storage path
        return Storage::disk('public')->url($this->og_image_path);
    }

    /**
     * Get the single theme settings instance
     */
    public static function getSettings()
    {
        return static::firstOrCreate([], [
            'site_name' => 'Teleman AI',
            'site_tagline' => 'Voice Calling Reimagined',
            'primary_color' => '#000000',
            'secondary_color' => '#ffffff',
            'can_register' => true,
            'copyright_text' => '© 2025 Teleman AI. All rights reserved.',
        ]);
    }
}
