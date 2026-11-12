<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class KycSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get a setting value with type casting
     */
    public static function get(string $key, $default = null)
    {
        return Cache::remember("kyc_setting_{$key}", 3600, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();
            
            if (!$setting) {
                return $default;
            }

            return self::castValue($setting->value, $setting->type);
        });
    }

    /**
     * Set a setting value
     */
    public static function set(string $key, $value): bool
    {
        $setting = self::where('key', $key)->first();
        
        if (!$setting) {
            return false;
        }

        $setting->value = self::prepareValue($value, $setting->type);
        $saved = $setting->save();

        if ($saved) {
            Cache::forget("kyc_setting_{$key}");
        }

        return $saved;
    }

    /**
     * Get all settings grouped by category
     */
    public static function getAllGrouped(): array
    {
        return Cache::remember('kyc_settings_all', 3600, function () {
            $settings = self::all();
            $grouped = [];

            foreach ($settings as $setting) {
                if (!isset($grouped[$setting->group])) {
                    $grouped[$setting->group] = [];
                }

                $grouped[$setting->group][] = [
                    'key' => $setting->key,
                    'value' => self::castValue($setting->value, $setting->type),
                    'type' => $setting->type,
                    'description' => $setting->description,
                ];
            }

            return $grouped;
        });
    }

    /**
     * Clear all settings cache
     */
    public static function clearCache(): void
    {
        Cache::forget('kyc_settings_all');
        $settings = self::all();
        foreach ($settings as $setting) {
            Cache::forget("kyc_setting_{$setting->key}");
        }
    }

    /**
     * Cast value based on type
     */
    protected static function castValue($value, string $type)
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Prepare value for storage
     */
    protected static function prepareValue($value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => $value ? 'true' : 'false',
            'json' => json_encode($value),
            default => (string) $value,
        };
    }
}
