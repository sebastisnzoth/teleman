<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TwilioGlobalConfig extends Model
{
    use HasFactory;
    protected $table = 'twilio_global_config';

    protected $fillable = [
        'account_sid',
        'auth_token',
        'api_key_sid',
        'api_key_secret',
        'twiml_app_sid',
        'webhook_url',
        'is_active',
        'verified_at',
    ];

    protected $hidden = [
        'auth_token',
        'api_key_secret',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'verified_at' => 'datetime',
    ];

    /**
     * Get the active global Twilio configuration
     */
    public static function active(): ?self
    {
        return self::where('is_active', true)->first();
    }

    /**
     * Get auth token (no encryption)
     */
    public function getDecryptedAuthToken(): string
    {
        return $this->auth_token;
    }

    /**
     * Get API key secret (no encryption)
     */
    public function getDecryptedApiKeySecret(): ?string
    {
        return $this->api_key_secret;
    }
}
