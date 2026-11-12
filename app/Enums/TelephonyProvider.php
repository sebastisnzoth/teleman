<?php

declare(strict_types=1);

namespace App\Enums;

enum TelephonyProvider: string
{
    case TWILIO = 'twilio';
    case PLIVO = 'plivo';

    public function label(): string
    {
        return match ($this) {
            self::TWILIO => 'Twilio',
            self::PLIVO => 'Plivo',
        };
    }

    /**
     * Get all capabilities for this provider.
     */
    public function capabilities(): array
    {
        return match ($this) {
            self::TWILIO => ['sms', 'mms', 'voice', 'webrtc', 'streaming', 'phone_numbers', 'sip_trunking'],
            self::PLIVO => ['sms', 'mms', 'voice', 'webrtc', 'streaming', 'phone_numbers'],
        };
    }

    /**
     * Get the credential field mapping for this provider.
     */
    public function credentialFields(): array
    {
        return match ($this) {
            self::TWILIO => [
                'account_id' => 'account_sid',
                'auth_token' => 'auth_token',
            ],
            self::PLIVO => [
                'account_id' => 'auth_id',
                'auth_token' => 'auth_token',
            ],
        };
    }

    /**
     * Twilio-only features hidden from the UI when locked to this provider.
     */
    public function hiddenFeatures(): array
    {
        return config("telephony.hidden_features.{$this->value}", []);
    }

    /**
     * Whether the given feature is hidden for this provider.
     */
    public function hides(string $feature): bool
    {
        return in_array($feature, $this->hiddenFeatures(), true);
    }
}
