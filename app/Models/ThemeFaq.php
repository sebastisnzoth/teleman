<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThemeFaq extends Model
{
    protected $fillable = [
        'question',
        'answer',
        'icon',
        'order',
        'is_active',
    ];

    protected $casts = [
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
