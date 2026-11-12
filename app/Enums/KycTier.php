<?php

namespace App\Enums;

enum KycTier: string
{
    case UNVERIFIED = 'unverified';
    case BASIC = 'basic';
    case BUSINESS = 'business';

    public function label(): string
    {
        return match ($this) {
            self::UNVERIFIED => 'Unverified',
            self::BASIC => 'Basic (Tier 1)',
            self::BUSINESS => 'Business (Tier 2)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::UNVERIFIED => '7-day grace period. Verify to access features.',
            self::BASIC => 'Phone verification required. Limited access.',
            self::BUSINESS => 'Full document verification. Unlimited access.',
        };
    }

    public function maxPhoneNumbers(): ?int
    {
        return match ($this) {
            self::UNVERIFIED => 0,
            self::BASIC => 3,
            self::BUSINESS => null, // unlimited
        };
    }

    public function maxCallsPerDay(): ?int
    {
        return match ($this) {
            self::UNVERIFIED => 0,
            self::BASIC => 100,
            self::BUSINESS => null, // unlimited
        };
    }

    public function maxDeposit(): ?float
    {
        return match ($this) {
            self::UNVERIFIED => 0,
            self::BASIC => 500,
            self::BUSINESS => null, // unlimited
        };
    }

    public function graceDays(): int
    {
        return match ($this) {
            self::UNVERIFIED => 7,
            default => 0,
        };
    }

    public function canAccessFeature(string $feature): bool
    {
        $features = match ($this) {
            self::UNVERIFIED => [],
            self::BASIC => [
                'phone_numbers',
                'campaigns',
                'calls',
                'sms',
                'contacts',
                'audio_files',
            ],
            self::BUSINESS => [
                'phone_numbers',
                'campaigns',
                'calls',
                'sms',
                'contacts',
                'audio_files',
                'ai_agents',
                'advanced_analytics',
                'bulk_operations',
                'api_access',
            ],
        };

        return in_array($feature, $features);
    }

    public function isHigherThan(KycTier $tier): bool
    {
        $hierarchy = [
            self::UNVERIFIED->value => 0,
            self::BASIC->value => 1,
            self::BUSINESS->value => 2,
        ];

        return $hierarchy[$this->value] > $hierarchy[$tier->value];
    }

    public function isLowerThan(KycTier $tier): bool
    {
        $hierarchy = [
            self::UNVERIFIED->value => 0,
            self::BASIC->value => 1,
            self::BUSINESS->value => 2,
        ];

        return $hierarchy[$this->value] < $hierarchy[$tier->value];
    }

    public static function getOptions(): array
    {
        return [
            self::UNVERIFIED->value => self::UNVERIFIED->label(),
            self::BASIC->value => self::BASIC->label(),
            self::BUSINESS->value => self::BUSINESS->label(),
        ];
    }
}
