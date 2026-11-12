<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class Page extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'content',
        'meta_title',
        'meta_description',
        'is_active',
        'last_updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function getBySlug(string $slug): ?self
    {
        return Cache::remember("page:{$slug}", 3600, function () use ($slug) {
            return static::where('slug', $slug)->first();
        });
    }

    public static function clearCache(string $slug): void
    {
        Cache::forget("page:{$slug}");
    }
}
