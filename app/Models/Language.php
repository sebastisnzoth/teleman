<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Language extends Model
{
    protected $fillable = [
        'code',
        'name',
        'native_name',
        'flag',
        'is_rtl',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected $casts = [
        'is_rtl' => 'boolean',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(Translation::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public static function getDefault(): ?self
    {
        return Cache::remember('default_language', 3600, function () {
            return static::where('is_default', true)->first()
                ?? static::where('code', 'en')->first();
        });
    }

    public static function setDefault(int $id): void
    {
        static::where('is_default', true)->update(['is_default' => false]);
        static::where('id', $id)->update(['is_default' => true]);
        Cache::forget('default_language');
        Cache::forget('active_languages');
    }
}
