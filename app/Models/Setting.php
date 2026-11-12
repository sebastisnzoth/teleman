<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = [
        'user_id',
        'key',
        'value',
        'encrypted',
        'is_global',
    ];

    protected $casts = [
        'encrypted' => 'boolean',
        'is_global' => 'boolean',
    ];

    /**
     * Get the user who owns this setting
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: Get global (admin-only) settings
     */
    public function scopeGlobal($query)
    {
        return $query->where('is_global', true);
    }

    /**
     * Scope: Get user-specific settings
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId)->where('is_global', false);
    }

    /**
     * Get setting value (decrypt if needed)
     */
    public function getValue()
    {
        if ($this->encrypted && $this->value) {
            try {
                return Crypt::decryptString($this->value);
            } catch (\Exception $e) {
                return null;
            }
        }

        return $this->value;
    }

    /**
     * Set setting value (encrypt if needed)
     */
    public function setValue($value, $shouldEncrypt = false)
    {
        $this->encrypted = $shouldEncrypt;
        
        if ($shouldEncrypt && $value) {
            $this->value = Crypt::encryptString($value);
        } else {
            $this->value = $value;
        }

        return $this;
    }

    /**
     * Helper: Get a setting value by key
     */
    public static function get($key, $userId = null, $default = null)
    {
        $query = static::where('key', $key);

        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('is_global', true);
        }

        $setting = $query->first();

        return $setting ? $setting->getValue() : $default;
    }

    /**
     * Helper: Set a setting value
     */
    public static function set($key, $value, $userId = null, $encrypted = false, $isGlobal = false)
    {
        // For global settings, we need a user_id (typically admin)
        // For non-global, user_id must be provided
        if ($isGlobal && !$userId) {
            // Get first admin user or use ID 1
            $userId = \App\Models\User::whereHas('roles', function($q) {
                $q->where('slug', 'admin');
            })->first()->id ?? 1;
        }

        $setting = static::updateOrCreate(
            [
                'user_id' => $userId,
                'key' => $key,
            ],
            [
                'encrypted' => $encrypted,
                'is_global' => $isGlobal,
            ]
        );

        $setting->setValue($value, $encrypted);
        $setting->save();

        return $setting;
    }
}
