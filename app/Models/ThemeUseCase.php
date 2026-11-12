<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThemeUseCase extends Model
{
    protected $fillable = [
        'icon',
        'title',
        'description',
        'items',
        'color',
        'order',
        'is_active',
    ];

    protected $casts = [
        'items' => 'array',
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
